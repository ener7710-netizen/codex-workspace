<?php
declare(strict_types=1);

namespace SEOJusAI\GSC;

use SEOJusAI\Core\EmergencyStop;

defined('ABSPATH') || exit;

/**
 * GSCClient
 *
 * Google Search Console client
 * Авторизація: Service Account (JWT)
 * ❗ Без OAuth
 * ❗ Без UI
 * ❗ Для AI / Gemini / backend
 */
final class GSCClient {

	private const API_BASE = 'https://www.googleapis.com/webmasters/v3';

	/**
	 * Отримує access token через Service Account
	 */
	private function get_access_token(): string {

		if ( EmergencyStop::is_active() ) {
			return '';
		}

		try {
			return GscTokenProvider::get_access_token();
		} catch ( \Throwable $e ) {
			return '';
		}
	}

	/**
	 * Перевірка підключення (Service Account ключ + токен).
	 */
	public function is_connected(): bool {
		try {
			$token = $this->get_access_token();
			return is_string($token) && $token !== '';
		} catch (\Throwable $e) {
			return false;
		}
	}

	/**
	 * GET /sites
	 *
	 * @return array<int,string>
	 */
	public function list_properties(): array {

		$token = $this->get_access_token();
		if ( $token === '' ) {
			return [];
		}

		$response = wp_remote_get(
			self::API_BASE . '/sites',
			[
				'timeout' => 20,
				'headers' => [
					'Authorization' => 'Bearer ' . $token,
				],
			]
		);

		if ( is_wp_error($response) ) {
			return [];
		}

		$data = json_decode((string) wp_remote_retrieve_body($response), true);

		if ( ! is_array($data) || empty($data['siteEntry']) ) {
			return [];
		}

		$sites = [];

		foreach ( $data['siteEntry'] as $row ) {
			if ( isset($row['siteUrl']) ) {
				$sites[] = (string) $row['siteUrl'];
			}
		}

		return $sites;
	}

	/**
	 * POST /searchAnalytics/query
	 *
	 * @param string $site
	 * @param array<string,mixed> $args
	 * @return array<int,array<string,mixed>>
	 */
	public function get_search_analytics(string $site, array $args = []): array {

		$token = $this->get_access_token();
		if ( $token === '' ) {
			return [];
		}

		$defaults = [
			'startDate'  => gmdate('Y-m-d', strtotime('-28 days')),
			'endDate'    => gmdate('Y-m-d'),
			'dimensions' => ['query', 'page'],
			'rowLimit'   => 500,
		];

		$payload = array_merge($defaults, $args);

		$url = self::API_BASE . '/sites/' . rawurlencode($site) . '/searchAnalytics/query';

		$response = wp_remote_post(
			$url,
			[
				'timeout' => 30,
				'headers' => [
					'Authorization' => 'Bearer ' . $token,
					'Content-Type'  => 'application/json',
				],
				'body' => wp_json_encode($payload),
			]
		);

		if ( is_wp_error($response) ) {
			return [];
		}

		$data = json_decode((string) wp_remote_retrieve_body($response), true);

		if ( ! is_array($data) || empty($data['rows']) ) {
			return [];
		}

		return $data['rows'];
	}
}
