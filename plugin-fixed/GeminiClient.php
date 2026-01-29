<?php
declare(strict_types=1);

namespace SEOJusAI\AI\Providers;

defined('ABSPATH') || exit;

/**
 * GeminiClient
 *
 * НИЗЬКОРІВНЕВИЙ HTTP-клієнт Gemini.
 *
 * ❌ НЕ провайдер
 * ❌ НЕ стратегія
 * ❌ НЕ знає про Engine / Manager
 *
 * ✔ тільки API виклик
 */
final class GeminiClient {

	private string $api_key;
	private string $model;

	public function __construct(
		string $api_key,
		string $model = 'models/gemini-1.5-pro'
	) {
		$this->api_key = trim($api_key);
		$this->model   = trim($model) !== '' ? trim($model) : 'models/gemini-1.5-pro';
	}

	public function is_ready(): bool {
		return $this->api_key !== '';
	}

	/**
	 * Chat-style wrapper (messages → prompt).
	 *
	 * @param array<string,mixed> $payload
	 * @param string $scope
	 * @return array{ok:bool,reply?:string,error?:string}
	 */
	public function analyze(array $payload, string $scope = 'default'): array {

		$messages = $payload['messages'] ?? null;
		if (!is_array($messages) || $messages === []) {
			$prompt = (string) ($payload['prompt'] ?? '');
			$prompt = trim($prompt);
		} else {
			// Конвертуємо messages у плоский prompt (детерміновано)
			$chunks = [];
			foreach ($messages as $m) {
				if (!is_array($m)) {
					continue;
				}
				$role = isset($m['role']) && is_string($m['role']) ? $m['role'] : 'user';
				$content = isset($m['content']) && is_string($m['content']) ? $m['content'] : '';
				$content = trim($content);
				if ($content === '') {
					continue;
				}
				$chunks[] = strtoupper($role) . ":\n" . $content;
			}
			$prompt = implode("\n\n", $chunks);
		}

		if (!$this->is_ready()) {
			return [
				'ok'    => false,
				'error' => 'Gemini API key is missing',
			];
		}

		if (!is_string($prompt) || trim($prompt) === '') {
			return [
				'ok'    => false,
				'error' => 'Gemini payload is empty',
			];
		}

		$text = $this->generate($prompt, (string) ($payload['mode'] ?? 'full'));

		if (!is_string($text) || trim($text) === '') {
			return [
				'ok'    => false,
				'error' => 'Gemini returned empty response',
			];
		}

		return [
			'ok'    => true,
			'reply' => $text,
		];
	}

	/**
	 * @param string $prompt
	 * @param string $mode
	 * @return string|null
	 */
	public function generate(string $prompt, string $mode = 'full'): ?string {

		if (!$this->is_ready()) {
			return null;
		}

		$endpoint = sprintf(
			'https://generativelanguage.googleapis.com/v1beta/%s:generateContent?key=%s',
			rawurlencode($this->model),
			rawurlencode($this->api_key)
		);

		$max_tokens = $mode === 'editor' ? 600 : 1500;

		$response = wp_remote_post($endpoint, [
			'timeout' => 30,
			'headers' => [
				'Content-Type' => 'application/json',
			],
			'body' => wp_json_encode([
				'contents' => [
					[
						'role'  => 'user',
						'parts' => [
							[ 'text' => $prompt ],
						],
					],
				],
				'generationConfig' => [
					'temperature'     => 0.2,
					'maxOutputTokens' => $max_tokens,
				],
			]),
		]);

		if (is_wp_error($response)) {
			return null;
		}

		$data = json_decode((string) wp_remote_retrieve_body($response), true);

		if (!is_array($data)) {
			return null;
		}

		$out = $data['candidates'][0]['content']['parts'][0]['text'] ?? null;
		return is_string($out) ? $out : null;
	}
}
