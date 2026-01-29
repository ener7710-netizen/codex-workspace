<?php
declare(strict_types=1);

namespace SEOJusAI\AI\Analyzer;

use SEOJusAI\AI\Chat\ChatPromptBuilder;
use SEOJusAI\AI\Client\AIClient;
use SEOJusAI\Utils\Logger;

defined('ABSPATH') || exit;

/**
 * AIReasoner
 *
 * Єдине місце виклику AI через AIClient.
 */
final class AIReasoner {

	/**
	 * Основний метод чату.
	 *
	 * @param array<string,mixed> $context
	 * @return array{ok:bool,reply:string,tasks?:array<int,mixed>,error?:string}
	 */
	public static function chat(array $context): array {

		$prompt = ChatPromptBuilder::build($context);

		if (!is_string($prompt) || trim($prompt) === '') {
			return self::error('AI prompt is empty');
		}

		try {
			$messages = [
				[
					'role'    => 'system',
					'content' =>
						'Ти досвідчений SEO-консультант і юридичний маркетолог. ' .
						'Ти відповідаєш як жива людина, а не бот. ' .
						'Ти спираєшся ТІЛЬКИ на аудит сторінки, її проблеми та SEO-контекст. ' .
						'Не вигадуй факти. Якщо можливо — формуй SEO-задачі.',
				],
				[
					'role'    => 'user',
					'content' => $prompt,
				],
			];

			$result = AIClient::chat([
				'messages' => $messages,
			], 'chat:' . (string) ((int) ($context['post_id'] ?? 0)));

		} catch (\Throwable $e) {
			if (defined('WP_DEBUG') && WP_DEBUG) {
				if (class_exists(Logger::class)) {
			Logger::error('ai_reasoner_error', ['message' => '[SEOJusAI AIReasoner] ' . $e->getMessage()]);
		}
			}
			return self::error('AI request failed: ' . $e->getMessage());
		}

		if (!is_array($result) || empty($result['ok'])) {
			return self::error((string) ($result['error'] ?? 'AI request failed'));
		}

		$raw = (string) ($result['reply'] ?? '');
		$raw = trim($raw);

		if ($raw === '') {
			return self::error('Empty response from AI');
		}

		// Спроба JSON (reply + tasks) — якщо OpenAI повернув структуру.
		$decoded = json_decode($raw, true);

		if (is_array($decoded)) {
			return [
				'ok'    => true,
				'reply' => (string) ($decoded['reply'] ?? ''),
				'tasks' => is_array($decoded['tasks'] ?? null) ? $decoded['tasks'] : [],
			];
		}

		return [
			'ok'    => true,
			'reply' => $raw,
			'tasks' => [],
		];
	}

	/**
	 * Формування структури помилки.
	 *
	 * @param string $message
	 * @return array{ok:bool,reply:string,error:string}
	 */
	private static function error(string $message): array {
		return [
			'ok'    => false,
			'reply' => '',
			'error' => $message,
		];
	}
}
