<?php
declare(strict_types=1);

namespace SEOJusAI\AI\Chat;

use SEOJusAI\Core\EmergencyStop;
use SEOJusAI\AI\Strategy\LegalAIStrategy;
use SEOJusAI\AI\Analyzer\AIReasoner;
use SEOJusAI\AI\Analyzer\AITaskExtractor;
use SEOJusAI\Utils\Logger;

defined('ABSPATH') || exit;

/**
 * LegalAIChat
 * ------------------------------------------------------------
 * ЄДИНИЙ оркестратор:
 *
 * audit → rule-strategy → (E-E-A-T + KBE) → AI chat → AI task extractor → UI
 *
 * ГАРАНТІЇ:
 * ✔ чат ніколи не ламає аудит
 * ✔ задачі НІКОГДА не порожні
 * ✔ AI може впасти — система живе
 * ✔ audit = SOURCE OF TRUTH
 *
 * ДОДАНО:
 * ✔ EmergencyStop
 * ✔ E-E-A-T контекст (якщо є)
 * ✔ KBE контекст (якщо є)
 * ✔ learning: збереження Q/A у KBE (best-effort)
 * ✔ гарантований prompt-block для E-E-A-T/KBE навіть якщо ChatPromptBuilder їх не підтримує
 */
final class LegalAIChat {

	public static function respond(
		int $post_id,
		string $message,
		bool $is_learning,
		int $user_id = 0
	): array {

		$post_id = (int) $post_id;
		$message = trim($message);
		$user_id = (int) $user_id;

		// 🛑 Emergency Stop
		if (class_exists(EmergencyStop::class) && EmergencyStop::is_active()) {
			return self::error('AI тимчасово вимкнено (Emergency Stop).');
		}

		if ($post_id <= 0 || $message === '') {
			return self::error('Некоректний запит.');
		}

		/**
		 * 0️⃣ ДАНІ АУДИТУ (SOURCE OF TRUTH)
		 */
		$data = get_post_meta($post_id, '_seojusai_analysis_data', true);

		if (!is_array($data) || empty($data)) {
			return self::error(
				'Для цієї сторінки ще не проводився аудит. Запусти аудит у вкладці «Аудит».'
			);
		}

		$facts      = (array) ($data['facts'] ?? []);
		$analysis   = (array) ($data['analysis'] ?? []);
		$base_tasks = (array) ($data['tasks'] ?? []);
		$score      = (int)   ($data['score'] ?? 0);

		/**
		 * 1️⃣ RULE-BASED СТРАТЕГІЯ (СТРАХОВКА)
		 */
		$strategy = LegalAIStrategy::build(
			$facts,
			[
				'analysis' => $analysis,
				'tasks'   => $base_tasks,
				'score'   => $score,
			]
		);

		$rule_tasks   = (array) ($strategy['tasks'] ?? []);
		$rule_schema  = (array) ($strategy['schema'] ?? []);
		$rule_explain = (array) ($strategy['explain'] ?? []);

		// ✅ Страховка: якщо rule_tasks порожній — використовуємо base_tasks
		if (empty($rule_tasks) && !empty($base_tasks)) {
			$rule_tasks = $base_tasks;
		}

		/**
		 * 2️⃣ E-E-A-T + KBE (ДОДАТКОВИЙ КОНТЕКСТ)
		 * ❗ НЕ ламає роботу, якщо модулів немає
		 */
		$eeat = self::get_eeat($post_id);
		$kbe  = self::get_kbe_context($post_id, $message);

		// ✅ Текстові блоки (гарантовано підуть у prompt навіть якщо ChatPromptBuilder їх не читає)
		$eeat_text = self::format_eeat_block($eeat);
		$kbe_text  = self::format_kbe_block($kbe);

		/**
		 * 3️⃣ КОНТЕКСТ ДЛЯ ЖИВОГО AI-ЧАТУ
		 */
		$context = [
			'post_id'     => $post_id,
			'message'     => $message,
			'is_learning' => $is_learning,
			'user_id'     => $user_id,

			'facts'    => $facts,
			'analysis' => $analysis,
			'tasks'    => $rule_tasks, // ⚠️ ВАЖНО: передаємо rule-задачі
			'score'    => $score,
			'explain'  => $rule_explain,

			// ➕ ДОДАНО (структурно)
			'eeat' => $eeat,
			'kbe'  => $kbe,

			// ➕ ДОДАНО (текстові блоки для prompt)
			'eeat_text' => $eeat_text,
			'kbe_text'  => $kbe_text,

			'page' => [
				'title' => (string) ($facts['meta']['title'] ?? ''),
				'url'   => (string) ($facts['url'] ?? ''),
				'h1'    => implode(', ', (array) ($facts['headings']['h1'] ?? [])),
			],
		];

		/**
		 * 4️⃣ ЖИВИЙ AI-ЧАТ (TEXT ONLY)
		 * AIReasoner::chat має прийняти контекст і сам побудувати prompt.
		 * (E-E-A-T + KBE вже у $context — AIReasoner може їх використати)
		 */
		$ai = [];
		try {
			$ai = AIReasoner::chat($context);
		} catch (\Throwable $e) {
			$ai = [
				'ok'    => false,
				'reply' => '',
			];
			if (defined('WP_DEBUG') && WP_DEBUG) {
				if (class_exists(Logger::class)) {
			Logger::error('legal_ai_chat_error', ['message' => '[SEOJusAI LegalAIChat] AIReasoner error: ' . $e->getMessage()]);
		}
			}
		}

		if (empty($ai['ok'])) {
			// ❗ AI може впасти — але система НЕ ЛАМАЄТЬСЯ
			$reply = 'Я бачу проблеми сторінки, але AI тимчасово недоступний. '
			       . 'Нижче — системні SEO-задачі для покращення.';
		} else {
			$reply = trim((string) ($ai['reply'] ?? ''));
			if ($reply === '') {
				$reply = 'AI не повернув відповіді. Нижче — системні SEO-задачі для покращення.';
			}
		}

		/**
		 * 5️⃣ 🔥 AI → SEO-ЗАДАЧІ (JSON ONLY)
		 * МОЖЕ ПОВЕРНУТИ []
		 */
		$ai_tasks = [];
		try {
			$ai_tasks = AITaskExtractor::extract([
				'facts'    => $facts,
				'analysis' => $analysis,
				'reply'    => $reply,
				// ➕ контекст для кращого витягу задач (не ламає, якщо екстрактор ігнорує)
				'eeat'     => $eeat,
				'kbe'      => $kbe,
			]);
		} catch (\Throwable $e) {
			$ai_tasks = [];
			if (defined('WP_DEBUG') && WP_DEBUG) {
				if (class_exists(Logger::class)) {
			Logger::error('legal_ai_chat_error', ['message' => '[SEOJusAI LegalAIChat] AITaskExtractor error: ' . $e->getMessage()]);
		}
			}
		}

		/**
		 * 6️⃣ ФІНАЛЬНИЙ ВИБІР ЗАДАЧ
		 * ❗ ГАРАНТІЯ: НЕ БУВАЄ ПУСТО
		 */
		$final_tasks = !empty($ai_tasks) ? $ai_tasks : $rule_tasks;

		// ✅ Якщо раптом і rule_tasks порожній — повертаємо base_tasks, а якщо і їх нема — хоча б один safe-task
		if (empty($final_tasks)) {
			$final_tasks = !empty($base_tasks) ? $base_tasks : [
				[
					'action'   => 'manual_review',
					'type'     => 'general',
					'priority' => 'medium',
					'post_id'  => $post_id,
					'auto'     => false,
					'source'   => 'chat:fallback',
					'desc'     => 'Перевірити сторінку вручну: структура, H1/H2, FAQ, schema, внутрішні посилання.',
				],
			];
		}

		/**
		 * 6.1️⃣ Learning: best-effort збереження Q/A в KBE
		 * (не впливає на чат, якщо KBE не підтримує)
		 */
		if ($is_learning) {
			self::maybe_store_kbe($post_id, $message, $reply, $user_id);
		}

		/**
		 * 7️⃣ ЛОГ ДІАЛОГУ
		 */
		self::append_chat_log($post_id, [
			'role'      => 'user',
			'message'   => $message,
			'timestamp' => current_time('mysql'),
		]);

		self::append_chat_log($post_id, [
			'role'      => 'assistant',
			'message'   => $reply,
			'timestamp' => current_time('mysql'),
		]);

		/**
		 * 8️⃣ ВІДПОВІДЬ У GUTENBERG (КОНТРАКТ НЕ ЛОМАТИ)
		 */
		return [
			'ok'               => true,
			'reply'            => $reply,
			'suggested_tasks'  => $final_tasks,
			'suggested_schema' => $rule_schema,
			'confidence'       => !empty($ai_tasks) ? 'high' : 'medium',

			'facts_summary' => [
				'title'      => (string) ($facts['meta']['title'] ?? ''),
				'h1'         => implode(', ', (array) ($facts['headings']['h1'] ?? [])),
				'word_count' => (int) ($facts['content']['word_count'] ?? 0),
				'score'      => $score,
				'updated_at' => (string) ($data['updated_at'] ?? ''),
			],

			// Додатково (не ламає UI якщо не використовується)
			'context_meta' => [
				'has_eeat' => !empty($eeat),
				'has_kbe'  => !empty($kbe),
			],

			// ➕ Додатково: текстові блоки (для дебага або UI)
			'context_text' => [
				'eeat' => $eeat_text,
				'kbe'  => $kbe_text,
			],
		];
	}

	/* ============================================================
	 * E-E-A-T
	 * ============================================================ */

	private static function get_eeat(int $post_id): array {
		try {
			if (class_exists('\SEOJusAI\Eeat\EeatRepository')) {
				$data = \SEOJusAI\Eeat\EeatRepository::get($post_id);
				return is_array($data) ? $data : [];
			}
		} catch (\Throwable $e) {}
		return [];
	}

	private static function format_eeat_block(array $eeat): string {

		if (empty($eeat)) {
			return '';
		}

		$author      = (string) ($eeat['author'] ?? ($eeat['expert'] ?? ''));
		$experience  = (string) ($eeat['experience'] ?? '');
		$cred        = (string) ($eeat['credentials'] ?? ($eeat['license'] ?? ''));
		$trust       = (string) ($eeat['trust'] ?? '');
		$law_basis   = (string) ($eeat['law_basis'] ?? '');
		$updated_at  = (string) ($eeat['updated_at'] ?? '');

		$lines = [];
		$lines[] = "E-E-A-T (дані з адмінки):";
		if ($author !== '')     $lines[] = "- Автор/Експерт: {$author}";
		if ($experience !== '') $lines[] = "- Досвід: {$experience}";
		if ($cred !== '')       $lines[] = "- Статус/ліцензія: {$cred}";
		if ($trust !== '')      $lines[] = "- Trust: {$trust}";
		if ($law_basis !== '')  $lines[] = "- Нормативна база/посилання: {$law_basis}";
		if ($updated_at !== '') $lines[] = "- Оновлено: {$updated_at}";

		return implode("\n", $lines) . "\n";
	}

	/* ============================================================
	 * KBE
	 * ============================================================ */

	/**
	 * Повертає масив коротких KBE підказок.
	 * Best-effort: якщо методів немає — повертає []
	 *
	 * @return array<int,string>
	 */
	private static function get_kbe_context(int $post_id, string $question): array {

		$out = [];

		try {

			// 1) KnowledgeBase::search($query, $limit)
			if (class_exists('\SEOJusAI\KBE\KnowledgeBase') && method_exists('\SEOJusAI\KBE\KnowledgeBase', 'search')) {
				$kb  = new \SEOJusAI\KBE\KnowledgeBase();
				$res = $kb->search($question, 5);

				if (is_array($res)) {
					foreach ($res as $row) {
						if (is_string($row) && $row !== '') {
							$out[] = $row;
							continue;
						}
						if (is_array($row) && !empty($row['text'])) {
							$out[] = (string) $row['text'];
						}
					}
				}
			}

			// 2) Repository::get_recent($post_id, $limit)
			if (empty($out) && class_exists('\SEOJusAI\KBE\Repository') && method_exists('\SEOJusAI\KBE\Repository', 'get_recent')) {
				$repo = new \SEOJusAI\KBE\Repository();
				$res  = $repo->get_recent($post_id, 5);

				if (is_array($res)) {
					foreach ($res as $row) {
						if (is_string($row) && $row !== '') {
							$out[] = $row;
							continue;
						}
						if (is_array($row) && !empty($row['text'])) {
							$out[] = (string) $row['text'];
						}
					}
				}

			}

		} catch (\Throwable $e) {}

		return array_values(array_filter($out));
	}

	private static function format_kbe_block(array $kbe): string {

		if (empty($kbe)) {
			return '';
		}

		$lines = [];
		$lines[] = "KBE (внутрішня база знань):";

		$i = 1;
		foreach ($kbe as $item) {
			$item = trim((string) $item);
			if ($item === '') {
				continue;
			}
			$lines[] = $i . '. ' . self::limit_text($item, 450);
			$i++;
			if ($i > 5) break;
		}

		return implode("\n", $lines) . "\n";
	}

	private static function maybe_store_kbe(int $post_id, string $q, string $a, int $user_id): void {
		try {

			$payload = [
				'post_id'    => $post_id,
				'user_id'    => $user_id,
				'question'   => $q,
				'answer'     => $a,
				'created_at' => time(),
				'type'       => 'chat',
			];

			// Repository::add(array $payload)
			if (class_exists('\SEOJusAI\KBE\Repository') && method_exists('\SEOJusAI\KBE\Repository', 'add')) {
				$repo = new \SEOJusAI\KBE\Repository();
				$repo->add($payload);
				return;
			}

			// KnowledgeBase::learn(array $payload)
			if (class_exists('\SEOJusAI\KBE\KnowledgeBase') && method_exists('\SEOJusAI\KBE\KnowledgeBase', 'learn')) {
				$kb = new \SEOJusAI\KBE\KnowledgeBase();
				$kb->learn($payload);
				return;
			}

			// fallback option (мінімально)
			$opt = get_option('seojusai_kbe_chat', []);
			$opt = is_array($opt) ? $opt : [];
			$opt[] = $payload;

			if (count($opt) > 50) {
				$opt = array_slice($opt, -50);
			}

			update_option('seojusai_kbe_chat', $opt, false);

		} catch (\Throwable $e) {}
	}

	private static function limit_text(string $text, int $max): string {
		$text = trim($text);
		if (mb_strlen($text) <= $max) return $text;
		return mb_substr($text, 0, $max) . '…';
	}

	/* ============================================================
	 * CHAT LOG
	 * ============================================================ */

	private static function append_chat_log(int $post_id, array $entry): void {

		$log = get_post_meta($post_id, '_seojusai_chat_log', true);
		if (!is_array($log)) {
			$log = [];
		}

		$log[] = $entry;

		if (count($log) > 30) {
			$log = array_slice($log, -30);
		}

		update_post_meta($post_id, '_seojusai_chat_log', $log);
	}

	public static function get_chat_log(int $post_id): array {
		$log = get_post_meta($post_id, '_seojusai_chat_log', true);
		return is_array($log) ? $log : [];
	}

	private static function error(string $msg): array {
		return [
			'ok'               => false,
			'reply'            => $msg,
			'suggested_tasks'  => [],
			'suggested_schema' => [],
			'confidence'       => 'low',
		];
	}
}
