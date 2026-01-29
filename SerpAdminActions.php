<?php
declare(strict_types=1);

namespace SEOJusAI\SERP;

use SEOJusAI\Tasks\TaskQueue;
use SEOJusAI\Utils\Logger;

defined('ABSPATH') || exit;

final class SerpAdminActions {

	public static function register(): void {
		add_action('admin_post_seojusai_serp_snapshot', [self::class, 'handle_snapshot']);
	}

	public static function handle_snapshot(): void {
		if (!current_user_can('manage_options')) {
			wp_die(esc_html__('Недостатньо прав доступу.', 'seojusai'), 403);
		}

		check_admin_referer('seojusai_serp_snapshot');

		$query = isset($_POST['serp_query']) ? sanitize_text_field((string) wp_unslash($_POST['serp_query'])) : '';
		if ($query === '') {
			$query = (string) parse_url((string) home_url(), PHP_URL_HOST);
		}

		$payload = [
			'query' => $query,
			'country' => isset($_POST['serp_country']) ? sanitize_key((string) wp_unslash($_POST['serp_country'])) : 'ua',
			'lang' => isset($_POST['serp_lang']) ? sanitize_key((string) wp_unslash($_POST['serp_lang'])) : 'uk',
			'requested_by' => (int) get_current_user_id(),
			'requested_at' => time(),
		];

		try {
			$queue = new TaskQueue();
			$queue->enqueue('serp_snapshot', $payload, 'serp_' . md5($query . '|' . $payload['country'] . '|' . $payload['lang']));
			wp_safe_redirect(add_query_arg(['page' => 'seojusai-serp', 'seojusai_notice' => 'serp_enqueued'], admin_url('admin.php')));
			exit;
		} catch (\Throwable $e) {
			if (class_exists(Logger::class)) {
				Logger::error('serp_snapshot_enqueue_failed', ['error' => $e->getMessage()]);
			}
			wp_safe_redirect(add_query_arg(['page' => 'seojusai-serp', 'seojusai_notice' => 'serp_failed'], admin_url('admin.php')));
			exit;
		}
	}
}
