<?php
declare(strict_types=1);

namespace SEOJusAI\KBE;

if ( ! defined('ABSPATH') ) {
	exit;
}

/**
 * KnowledgeBase
 *
 * Human-in-the-loop навчання AI.
 * Перетворює дії користувача (схвалення/відхилення) у довгострокову пам'ять системи.
 */
final class KnowledgeBase {

	private Repository $repo;

	public function __construct() {
		$this->repo = new Repository();
	}

	/**
	 * Підписка на події системи.
	 */
	public function register(): void {
		// Викликається, коли користувач натискає "Reject" або пише коментар до задачі
		add_action('seojusai/kbe/learn_from_feedback', [$this, 'learn'], 10, 1);
	}

	/**
	 * Навчання на основі фідбеку.
	 * * @param array $data ['type', 'post_id', 'feedback', 'is_negative']
	 */
	public function learn(array $data): void {
		$post_id = (int) ($data['post_id'] ?? 0);
		$type    = sanitize_key($data['type'] ?? 'general');
		$feedback = sanitize_text_field($data['feedback'] ?? '');

		if ($feedback === '') {
			return;
		}

		// Створюємо хеш контексту (наприклад, поєднання типу завдання та шаблону сторінки)
		$context_hash = md5($type . (string)get_post_type($post_id));

		$record = [
			'context_hash' => $context_hash,
			'rule_key'     => 'user_preference_' . time(),
			'rule_value'   => $feedback,
			'error_weight' => (!empty($data['is_negative'])) ? 1 : 0,
		];

		$this->repo->upsert($record);
	}

	/**
	 * Отримати контекстні правила для додавання в System Prompt.
	 * Це "пам'ять", яку ми передамо Gemini: "Користувач раніше просив не робити Х".
	 */
	public function get_instructions_for_ai(int $post_id, string $type): string {
		$context_hash = md5($type . (string)get_post_type($post_id));
		$rules = $this->repo->get_by_context($context_hash);

		if (empty($rules)) {
			return '';
		}

		$instruction = "\n### ПРАВИЛА НА ОСНОВІ ПОПЕРЕДНЬОГО ФІДБЕКУ:\n";
		foreach ($rules as $rule) {
			$prefix = ($rule['error_weight'] > 0) ? "- УНИКАТИ: " : "- ПРАВИЛО: ";
			$instruction .= $prefix . $rule['rule_value'] . "\n";
		}

		return $instruction;
	}
}
