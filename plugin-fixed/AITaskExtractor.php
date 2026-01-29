<?php
declare(strict_types=1);

namespace SEOJusAI\AI\Analyzer;

defined('ABSPATH') || exit;

/**
 * AITaskExtractor
 * ------------------------------------------------------------
 * ЄДИНЕ місце, де AI генерує СТРУКТУРНІ SEO-ЗАВДАННЯ.
 *
 * ГАРАНТІЇ:
 * ✔ завдання НІКОЛИ не ламають систему
 * ✔ JSON строго валідний
 * ✔ fallback на rule-based можливий вище (LegalAIChat)
 *
 * ❌ не чат
 * ❌ не пояснення
 * ❌ не впливає на аудит
 */
final class AITaskExtractor {

	/**
	 * Витягує SEO-задачі з AI-відповіді
	 *
	 * @param array{
	 *   facts: array,
	 *   analysis: array,
	 *   reply: string
	 * } $context
	 *
	 * @return array<int, array{
	 *   action:string,
	 *   priority:string,
	 *   auto:bool
	 * }>
	 */
	public static function extract(array $context): array {

		$prompt = self::build_prompt($context);
		if ($prompt === '') {
			return [];
		}

		$apiKey = self::get_api_key();
		if ($apiKey === '') {
			return [];
		}

		$response = self::call_openai($apiKey, $prompt);
		if (!is_string($response) || trim($response) === '') {
			return [];
		}

		$decoded = json_decode($response, true);
		if (!is_array($decoded)) {
			return [];
		}

		$clean = [];

		foreach ($decoded as $task) {
			if (
				is_array($task)
				&& isset($task['action'], $task['priority'])
				&& is_string($task['action'])
				&& is_string($task['priority'])
			) {
				$clean[] = [
					'action'   => trim($task['action']),
					'priority' => in_array($task['priority'], ['high', 'medium', 'low'], true)
						? $task['priority']
						: 'medium',
					'auto'     => (bool) ($task['auto'] ?? false),
				];
			}
		}

		return $clean;
	}

	/* ============================================================
	 * PROMPT
	 * ============================================================ */

	private static function build_prompt(array $context): string {

		$facts    = (array) ($context['facts'] ?? []);
		$analysis = (array) ($context['analysis'] ?? []);
		$reply   = trim((string) ($context['reply'] ?? ''));

		if ($reply === '' && empty($analysis)) {
			return '';
		}

		$title = (string) ($facts['meta']['title'] ?? '');
		$h1    = implode(', ', (array) ($facts['headings']['h1'] ?? []));
		$words = (int) ($facts['content']['word_count'] ?? 0);

		$issues = [];
		foreach ($analysis as $row) {
			if (!empty($row['desc'])) {
				$issues[] = '- ' . $row['desc'];
			}
		}

		$issuesText = $issues
			? implode("\n", $issues)
			: 'Явних проблем не виявлено.';

		return trim("
Ти — SEO-движок.
Ти формуєш КОНКРЕТНІ SEO-ЗАВДАННЯ для сторінки.

❗ СУВОРО:
- ПОВЕРТАЙ ЛИШЕ ЧИСТИЙ JSON
- НІЯКОГО тексту
- НІЯКИХ пояснень
- НІЯКОГО markdown

ФОРМАТ ВІДПОВІДІ:
[
  {
    \"action\": \"Що конкретно зробити\",
    \"priority\": \"high|medium|low\",
    \"auto\": false
  }
]

КОНТЕКСТ СТОРІНКИ:
Title: {$title}
H1: {$h1}
Words: {$words}

ПРОБЛЕМИ АУДИТУ:
{$issuesText}

ВІДПОВІДЬ AI-КОНСУЛЬТАНТА:
{$reply}

СФОРМУЙ 3–6 SEO-ЗАВДАНЬ.
");
	}

	/* ============================================================
	 * OPENAI
	 * ============================================================ */

	private static function call_openai(string $apiKey, string $prompt): ?string {

		$body = [
			'model' => 'gpt-4o-mini',
			'messages' => [
				[
					'role'    => 'system',
					'content' => 'Ти повертаєш ТІЛЬКИ JSON. Без тексту.'
				],
				[
					'role'    => 'user',
					'content' => $prompt
				],
			],
			'temperature' => 0.2,
			'max_tokens'  => 450,
		];

		$response = wp_remote_post(
			'https://api.openai.com/v1/chat/completions',
			[
				'timeout' => 30,
				'headers' => [
					'Content-Type'  => 'application/json',
					'Authorization' => 'Bearer ' . $apiKey,
				],
				'body' => wp_json_encode($body, JSON_UNESCAPED_UNICODE),
			]
		);

		if (is_wp_error($response)) {
			return null;
		}

		if ((int) wp_remote_retrieve_response_code($response) !== 200) {
			return null;
		}

		$raw = wp_remote_retrieve_body($response);
		if (!is_string($raw) || $raw === '') {
			return null;
		}

		$json = json_decode($raw, true);
		if (!is_array($json)) {
			return null;
		}

		return $json['choices'][0]['message']['content'] ?? null;
	}

	/* ============================================================
	 * API KEY
	 * ============================================================ */

	private static function get_api_key(): string {

		$key = apply_filters('seojusai/openai_key', '');
		if (is_string($key) && trim($key) !== '') {
			return trim($key);
		}

		$opt = get_option('seojusai_openai_key');
		return is_string($opt) ? trim($opt) : '';
	}
}
