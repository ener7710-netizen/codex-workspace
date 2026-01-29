<?php
declare(strict_types=1);

namespace SEOJusAI\Admin;

use SEOJusAI\Tasks\TaskQueue;
use SEOJusAI\Utils\Logger;

defined('ABSPATH') || exit;

use SEOJusAI\Admin\MetaBoxes\ManualLockMetaBox;
use SEOJusAI\Admin\ListColumns\AutopilotLockColumn;

final class AdminActions {

	public static function register(): void {
		add_action('admin_post_seojusai_enqueue_page_audit', [self::class, 'enqueue_page_audit']);
		add_action('admin_post_seojusai_enqueue_draft_from_competitors', [self::class, 'enqueue_draft_from_competitors']);
	}

	public static function enqueue_page_audit(): void {
		if (!current_user_can('manage_options')) {
			wp_die(esc_html__('Недостатньо прав доступу.', 'seojusai'), 403);
		}

		check_admin_referer('seojusai_enqueue_page_audit');

		$post_id = isset($_POST['post_id']) ? (int) wp_unslash($_POST['post_id']) : 0;
		if ($post_id <= 0) {
			wp_safe_redirect(add_query_arg(['seojusai_notice' => 'audit_missing_post'], wp_get_referer() ?: admin_url('admin.php?page=seojusai')));
			exit;
		}

		try {
			$queue = new TaskQueue();
			$queue->enqueue('page_audit', ['post_id' => $post_id], 'audit_' . $post_id);
			wp_safe_redirect(add_query_arg(['seojusai_notice' => 'audit_enqueued'], wp_get_referer() ?: admin_url('admin.php?page=seojusai')));
			exit;
		} catch (\Throwable $e) {
			if (class_exists(Logger::class)) {
				Logger::error('admin_enqueue_audit_failed', ['post_id' => $post_id, 'error' => $e->getMessage()]);
			}
			wp_safe_redirect(add_query_arg(['seojusai_notice' => 'audit_failed'], wp_get_referer() ?: admin_url('admin.php?page=seojusai')));
			exit;
		}
	}

	public static function enqueue_draft_from_competitors(): void {
		if (!current_user_can('edit_pages')) {
			wp_die(esc_html__('Недостатньо прав доступу.', 'seojusai'), 403);
		}

		check_admin_referer('seojusai_enqueue_draft_from_competitors');

		$post_id = isset($_POST['post_id']) ? (int) wp_unslash($_POST['post_id']) : 0;
		if ($post_id <= 0) {
			wp_safe_redirect(add_query_arg(['seojusai_notice' => 'draft_missing_post'], wp_get_referer() ?: admin_url('admin.php?page=seojusai-autopilot')));
			exit;
		}

		try {
			$queue = new TaskQueue();
			$queue->enqueue('draft_from_competitors', ['post_id' => $post_id], 'draft_comp_' . $post_id);
			wp_safe_redirect(add_query_arg(['seojusai_notice' => 'draft_enqueued'], wp_get_referer() ?: admin_url('admin.php?page=seojusai-autopilot')));
			exit;
		} catch (\Throwable $e) {
			if (class_exists(Logger::class)) {
				Logger::error('admin_enqueue_draft_failed', ['post_id' => $post_id, 'error' => $e->getMessage()]);
			}
			wp_safe_redirect(add_query_arg(['seojusai_notice' => 'draft_failed'], wp_get_referer() ?: admin_url('admin.php?page=seojusai-autopilot')));
			exit;
		}
	}
}
