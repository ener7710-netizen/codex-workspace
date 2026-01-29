<?php
declare(strict_types=1);

namespace SEOJusAI\AI\Http;

use SEOJusAI\AI\AIConfig;

defined('ABSPATH') || exit;

/**
 * HttpClient
 * Єдиний транспорт: direct або proxy.
 */
final class HttpClient {

	/**
	 * @param array<string,string> $headers
	 * @param array<string,mixed>|string $body
	 * @return array{ok:bool,code:int,body:string,error?:string}
	 */
	public static function post(string $url, array $headers, $body, int $timeout = 30): array {

		$target_url = $url;

		// Proxy режим: відправляємо на proxy_url, а url стає payload
		if (AIConfig::proxy_enabled() && AIConfig::proxy_url() !== '') {
			$target_url = AIConfig::proxy_url();
			$body = [
				'target'  => $url,
				'headers' => $headers,
				'body'    => $body,
			];
			$headers = [
				'Content-Type' => 'application/json',
			];
		}

		$args = [
			'headers' => $headers,
			'timeout' => $timeout,
			'body'    => is_string($body) ? $body : wp_json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
		];

		$res = wp_remote_post($target_url, $args);

		if (is_wp_error($res)) {
			return [
				'ok'    => false,
				'code'  => 0,
				'body'  => '',
				'error' => $res->get_error_message(),
			];
		}

		$code = (int) wp_remote_retrieve_response_code($res);
		$resp_body = (string) wp_remote_retrieve_body($res);

		return [
			'ok'   => $code >= 200 && $code < 300,
			'code' => $code,
			'body' => $resp_body,
		];
	}
}
