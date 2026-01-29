<?php
declare(strict_types=1);

namespace SEOJusAI\AI\Providers;

defined('ABSPATH') || exit;

/**
 * OpenAIClient
 *
 * НИЗЬКОРІВНЕВИЙ HTTP-клієнт OpenAI.
 *
 * ❌ НЕ провайдер
 * ❌ НЕ стратегія
 *
 * ✔ тільки виклик API
 */
final class OpenAIClient {

	private string $api_key;
	private string $model;

	public function __construct(
		string $api_key,
		string $model = 'gpt-4.1'
	) {
		$this->api_key = trim($api_key);
		$this->model   = trim($model) !== '' ? trim($model) : 'gpt-4.1';
	}

	public function is_ready(): bool {
		return $this->api_key !== '';
	}

	/**
	 * Chat completions (messages API).
	 *
	 * @param array<string,mixed> $payload
	 * @return array{ok:bool,reply?:string,error?:string,raw?:array<string,mixed>}
	 */
	public function chat(array $payload): array {

		if (!$this->is_ready()) {
			return [
				'ok'    => false,
				'error' => 'OpenAI API key is missing',
			];
		}

		$messages = $payload['messages'] ?? null;
		if (!is_array($messages) || $messages === []) {
			$prompt = (string) ($payload['prompt'] ?? '');
			$prompt = trim($prompt);
			if ($prompt === '') {
				return [
					'ok'    => false,
					'error' => 'OpenAI payload is empty',
				];
			}
			$messages = [
				[
					'role'    => 'user',
					'content' => $prompt,
				],
			];
		}

		$endpoint   = 'https://api.openai.com/v1/chat/completions';
		$max_tokens = isset($payload['max_tokens']) && is_numeric($payload['max_tokens']) ? (int) $payload['max_tokens'] : 1200;
		$temperature = isset($payload['temperature']) && is_numeric($payload['temperature']) ? (float) $payload['temperature'] : 0.2;

		$response = wp_remote_post($endpoint, [
			'timeout' => 45,
			'headers' => [
				'Authorization' => 'Bearer ' . $this->api_key,
				'Content-Type'  => 'application/json',
			],
			'body' => wp_json_encode([
				'model'       => $this->model,
				'messages'    => $messages,
				'temperature' => $temperature,
				'max_tokens'  => $max_tokens,
			]),
		]);

		if (is_wp_error($response)) {
			return [
				'ok'    => false,
				'error' => 'OpenAI request failed: ' . $response->get_error_message(),
			];
		}

		$body = (string) wp_remote_retrieve_body($response);
		$data = json_decode($body, true);

		if (!is_array($data)) {
			return [
				'ok'    => false,
				'error' => 'Invalid OpenAI response',
			];
		}

		$reply = $data['choices'][0]['message']['content'] ?? '';
		$reply = is_string($reply) ? $reply : '';

		if (trim($reply) === '') {
			return [
				'ok'    => false,
				'error' => 'Empty OpenAI reply',
				'raw'   => $data,
			];
		}

		return [
			'ok'    => true,
			'reply' => $reply,
			'raw'   => $data,
		];
	}

	/**
	 * Legacy prompt generation (system prompt only).
	 *
	 * @param string $prompt
	 * @param string $mode
	 * @return string|null
	 */
	public function generate(string $prompt, string $mode = 'full'): ?string {

		if (!$this->is_ready()) {
			return null;
		}

		$endpoint   = 'https://api.openai.com/v1/chat/completions';
		$max_tokens = $mode === 'editor' ? 600 : 1500;

		$response = wp_remote_post($endpoint, [
			'timeout' => 30,
			'headers' => [
				'Authorization' => 'Bearer ' . $this->api_key,
				'Content-Type'  => 'application/json',
			],
			'body' => wp_json_encode([
				'model'       => $this->model,
				'messages'    => [
					[ 'role' => 'system', 'content' => $prompt ],
				],
				'temperature' => 0.2,
				'max_tokens'  => $max_tokens,
			]),
		]);

		if (is_wp_error($response)) {
			return null;
		}

		$data = json_decode((string) wp_remote_retrieve_body($response), true);

		if (!is_array($data)) {
			return null;
		}

		$out = $data['choices'][0]['message']['content'] ?? null;
		return is_string($out) ? $out : null;
	}

	/**
	 * Embeddings
	 *
	 * @return array<float>|null
	 */
	public function embed(string $input, string $embedding_model = 'text-embedding-3-large'): ?array {

		if (!$this->is_ready()) {
			return null;
		}

		$endpoint = 'https://api.openai.com/v1/embeddings';
		$response = wp_remote_post($endpoint, [
			'timeout' => 30,
			'headers' => [
				'Authorization' => 'Bearer ' . $this->api_key,
				'Content-Type'  => 'application/json',
			],
			'body' => wp_json_encode([
				'model' => $embedding_model,
				'input' => $input,
			]),
		]);

		if (is_wp_error($response)) {
			return null;
		}

		$data = json_decode((string) wp_remote_retrieve_body($response), true);
		$vec = $data['data'][0]['embedding'] ?? null;
		if (!is_array($vec)) {
			return null;
		}

		$out = [];
		foreach ($vec as $v) {
			$out[] = (float) $v;
		}
		return $out;
	}
}
