<?php
declare(strict_types=1);

namespace SEOJusAI\AI\Chat;

defined('ABSPATH') || exit;

/**
 * ChatPromptBuilder
 * ------------------------------------------------------------
 * Формує ЖИВИЙ prompt для AI-чату.
 *
 * РОЛЬ:
 * - перетворює аудит у діалог
 * - змушує AI МИСЛИТИ, а не перелічувати
 * - дозволяє ставити уточнюючі питання
 * - враховує E-E-A-T та KBE (якщо доступні)
 */
final class ChatPromptBuilder {

	public static function build(array $context): string {

		$facts    = (array) ($context['facts'] ?? []);
		$analysis = (array) ($context['analysis'] ?? []);
		$tasks    = (array) ($context['tasks'] ?? []);
		$message  = trim((string) ($context['message'] ?? ''));

		$score = (int) ($context['score'] ?? 0);

		// ➕ ДОДАНО
		$eeatText = trim((string) ($context['eeat_text'] ?? ''));
		$kbeText  = trim((string) ($context['kbe_text'] ?? ''));

		$page    = self::page_context($facts, $context);
		$audit   = self::audit_context($analysis, $tasks);
		$userMsg = $message !== '' ? $message : 'Користувач очікує пояснення або поради.';

		$extraContext = '';
		if ($eeatText !== '' || $kbeText !== '') {
			$extraContext = "
================================
ЕКСПЕРТНІСТЬ ТА КОНТЕКСТ
================================
" . trim($eeatText . "\n\n" . $kbeText);
		}

		return trim("
Ти — SEOJusAI Autopilot.
Ти досвідчений SEO-аналітик і юридичний маркетолог.
Ти СПІЛКУЄШСЯ з людиною, а не відповідаєш як бот.

❗ ГОЛОВНЕ:
- Не повторюй аудит списком
- Не пиши шаблонно
- Думай, аналізуй, пояснюй
- Якщо бачиш проблему — поясни ЧОМУ вона важлива
- Якщо задачі відсутні — СФОРМУЙ їх сам на основі аудиту
- Враховуй юридичну специфіку та ризики
- Не вигадуй фактів і норм закону

================================
КОНТЕКСТ СТОРІНКИ
================================
{$page}

================================
АНАЛІЗ СИСТЕМИ
================================
{$audit}
{$extraContext}

================================
ПОВІДОМЛЕННЯ КОРИСТУВАЧА
================================
{$userMsg}

================================
ФОРМАТ ВІДПОВІДІ
================================
- Живий текст
- Людська мова
- Без маркованих списків, якщо користувач не просив
- Можеш поставити 1 уточнююче питання
");
	}

	/* ============================================================
	 * PAGE CONTEXT
	 * ============================================================ */

	private static function page_context(array $facts, array $context): string {

		$title = (string) ($facts['meta']['title'] ?? '');
		$h1    = implode(', ', (array) ($facts['headings']['h1'] ?? []));
		$words = (int) ($facts['content']['word_count'] ?? 0);
		$score = (int) ($context['score'] ?? 0);

		return trim("
Title: {$title}
H1: {$h1}
Обсяг тексту: {$words} слів
SEO Score: {$score}/100
");
	}

	/* ============================================================
	 * AUDIT CONTEXT
	 * ============================================================ */

	private static function audit_context(array $analysis, array $tasks): string {

		$out = [];

		foreach ($analysis as $row) {
			$status = $row['status'] ?? '';
			$desc   = $row['desc'] ?? '';
			if ($desc !== '') {
				$out[] = "- {$status}: {$desc}";
			}
		}

		if (!empty($tasks)) {
			$out[] = "";
			$out[] = "Системні задачі:";
			foreach ($tasks as $task) {
				$action   = $task['action'] ?? '';
				$priority = $task['priority'] ?? 'medium';
				if ($action !== '') {
					$out[] = "- {$action} (пріоритет: {$priority})";
				}
			}
		}

		return $out
			? implode("\n", $out)
			: "Явних задач ще не сформовано. AI повинен сам їх визначити.";
	}
}
