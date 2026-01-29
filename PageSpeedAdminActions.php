<?php
declare(strict_types=1);

namespace SEOJusAI\PageSpeed;

use SEOJusAI\Tasks\TaskQueue;
use SEOJusAI\Utils\Logger;

defined('ABSPATH') || exit;

final class PageSpeedAdminActions {

	public static function register(): void {
		add_action('admin_post_seojusai_pagespeed_snapshot', [self::class, 'handle_snapshot']);
	}

	public static function handle_snapshot(): void {
		if (!current_user_can('manage_options')) {
			wp_die(esc_html__('Недостатньо прав доступу.', 'seojusai'), 403);
		}

		check_admin_referer('seojusai_pagespeed_snapshot');

		$url = isset($_POST['pagespeed_url'])
			? esc_url_raw((string) wp_unslash($_POST['pagespeed_url']))
			: (string) home_url('/');

		if ($url === '') {
			$url = (string) home_url('/');
		}

		$strategy = isset($_POST['pagespeed_strategy'])
			? sanitize_key((string) wp_unslash($_POST['pagespeed_strategy']))
			: 'mobile';
		$strategy = ($strategy === 'desktop') ? 'desktop' : 'mobile';

		$payload = [
			'url' => $url,
			'strategy' => $strategy,
			'requested_by' => (int) get_current_user_id(),
			'requested_at' => time(),
			'priority' => 'low',
		];

		try {
			$queue = new TaskQueue();
			$key = 'pagespeed_' . md5($url . '|' . $strategy);
			$queue->enqueue('pagespeed_snapshot', $payload, $key);

			wp_safe_redirect(add_query_arg(
				['page' => 'seojusai-pagespeed', 'seojusai_notice' => 'pagespeed_enqueued', 'url' => rawurlencode($url), 'strategy' => $strategy],
				admin_url('admin.php')
			));
			exit;
		} catch (\Throwable $e) {
			if (class_exists(Logger::class)) {
				Logger::error('pagespeed_snapshot_enqueue_failed', ['error' => $e->getMessage()]);
			}
			wp_safe_redirect(add_query_arg(
				['page' => 'seojusai-pagespeed', 'seojusai_notice' => 'pagespeed_failed'],
				admin_url('admin.php')
			));
			exit;
		}
	}
}
