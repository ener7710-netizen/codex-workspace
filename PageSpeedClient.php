<?php
declare(strict_types=1);

namespace SEOJusAI\PageSpeed;

use SEOJusAI\Snapshots\SnapshotRepository;
use SEOJusAI\Core\EmergencyStop;

defined('ABSPATH') || exit;

/**
 * PageSpeedClient
 * * Клієнт для роботи з Google PageSpeed Insights API.
 * Збирає технічні метрики та автоматично зберігає їх у SnapshotRepository.
 */
final class PageSpeedClient {

	private const API_ENDPOINT = 'https://pagespeedonline.googleapis.com/pagespeedonline/v5/runPagespeed';

	/**
	 * Отримання API ключа з налаштувань.
	 */
	public function get_api_key(): string {
		$key = get_option('seojusai_pagespeed_key', '');
		return is_string($key) ? trim($key) : '';
	}

	/**
	 * Запуск аналізу PageSpeed + ЗБЕРЕЖЕННЯ SNAPSHOT
	 *
	 * @param string $url Повна URL-адреса сторінки.
	 * @param string $strategy 'mobile' або 'desktop'.
	 * @return array Результат аналізу або опис помилки.
	 */
	public function analyze(string $url, string $strategy = 'mobile'): array {

		// Захист від запуску при активному EmergencyStop
		if (class_exists('\\SEOJusAI\\Core\\EmergencyStop') && EmergencyStop::is_active()) {
			return ['ok' => false, 'error' => 'Emergency Stop is active'];
		}

        // Збільшуємо ліміт часу для важкого запиту.
        // Використовувати suppress оператор @ не рекомендується, оскільки це приховує реальні помилки.
        // Перевіримо існування функції та виконаємо без подавлення помилок. Якщо виклик
        // недоступний (наприклад, у режимі safe_mode), PHP виведе попередження в логах,
        // але робота скрипта не перерветься.
        if (function_exists('set_time_limit')) {
            try {
                set_time_limit(120);
            } catch (\Throwable $e) {
                // Продовжуємо без зміни ліміту; логувати помилку через error_log
                if (function_exists('error_log')) {
                    error_log('SEOJusAI PageSpeedClient: set_time_limit failed: ' . $e->getMessage());
                }
            }
        }

		$key = $this->get_api_key();

		if ($key === '') {
			return [
				'ok'    => false,
				'code'  => 0,
				'error' => 'PageSpeed API key not set',
				'data'  => [],
			];
		}

		$request_url = add_query_arg(
			[
				'url'      => $url,
				'strategy' => $strategy,
				'key'      => $key,
			],
			self::API_ENDPOINT
		);

		$response = wp_remote_get(
			$request_url,
			[
				'timeout'     => 90,
				'httpversion' => '1.1',
				'headers'     => [
					'Accept' => 'application/json',
				],
			]
		);

		if (is_wp_error($response)) {
			return [
				'ok'    => false,
				'code'  => 0,
				'error' => $response->get_error_message(),
				'data'  => [],
			];
		}

		$code = (int) wp_remote_retrieve_response_code($response);
		$body = wp_remote_retrieve_body($response);
		$data = json_decode((string) $body, true);

		if ($code !== 200 || !is_array($data)) {
			return [
				'ok'    => false,
				'code'  => $code,
				'error' => $body ?: 'Invalid PageSpeed response',
				'data'  => [],
			];
		}

		/* ================= SNAPSHOT ================= */

		// Формуємо ID сайту на основі хоста
		$host = parse_url($url, PHP_URL_HOST) ?: $url;
		$site_id = (int) crc32((string) $host);

		$snapshots = new SnapshotRepository();

		// Зберігаємо тільки найважливіші метрики Lighthouse, щоб не забивати БД
		$snapshots->insert(
			'pagespeed',
			$site_id,
			[
				'url'      => $url,
				'strategy' => $strategy,
				'metrics'  => [
					'performance' => $data['lighthouseResult']['categories']['performance']['score'] ?? 0,
					'lcp'         => $data['lighthouseResult']['audits']['largest-contentful-paint']['displayValue'] ?? '',
					'cls'         => $data['lighthouseResult']['audits']['cumulative-layout-shift']['displayValue'] ?? '',
					'fcp'         => $data['lighthouseResult']['audits']['first-contentful-paint']['displayValue'] ?? '',
					'speed_index' => $data['lighthouseResult']['audits']['speed-index']['displayValue'] ?? '',
				],
				// Повний результат можна за потреби зберегти в meta або окреме поле
				'raw_result_summary' => isset($data['lighthouseResult']['categories']) ? 'Captured' : 'Empty'
			]
		);

		return [
			'ok'   => true,
			'code' => 200,
			'data' => $data,
		];
	}
}
