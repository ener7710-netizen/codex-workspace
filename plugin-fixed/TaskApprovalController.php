<?php
declare(strict_types=1);

namespace SEOJusAI\Admin\Confirmations;

use SEOJusAI\Executors\ContentExecutor;
use SEOJusAI\Executors\MetaExecutor;
use SEOJusAI\Executors\SchemaExecutor;
use SEOJusAI\Snapshots\SnapshotService;
use SEOJusAI\Input\Input;

defined('ABSPATH') || exit;

final class TaskApprovalController {

	public static function handle(): void {

		if (
			! current_user_can('manage_options') ||
			null === Input::post('seojusai_tasks') ||
			'' === (string) Input::post('_wpnonce') ||
			! wp_verify_nonce((string) Input::post('_wpnonce'), 'seojusai_confirm_tasks')
		) {
			return;
		}

		$approved = array_map('intval', (array) Input::post('seojusai_tasks'));
		$tasks    = (array) get_option('seojusai_pending_tasks', []);

		$executors = [
			new ContentExecutor(),
			new MetaExecutor(),
			new SchemaExecutor(),
		];

		foreach ( $approved as $index ) {

			if ( ! isset($tasks[$index]) ) {
				continue;
			}

			$task = (array) $tasks[$index];
			$type = (string) ($task['type'] ?? '');
			$post_id = (int) ($task['post_id'] ?? 0);

			/**
			 * ✅ ШАГ 7: Snapshot BEFORE apply
			 * Не ламає існуючих executors.
			 */
			$snapshot_id = 0;
			if ( $post_id > 0 && class_exists(SnapshotService::class) ) {
				try {
					$snapshot_id = (int) (new SnapshotService())->capture_post($post_id, 'pre_apply');
				} catch ( \Throwable $e ) {
					// snapshot необовʼязковий, не валимо apply
					$snapshot_id = 0;
				}
			}

			// додаємо snapshot_id у task (executors можуть ігнорувати)
			if ( $snapshot_id > 0 ) {
				$task['snapshot_id'] = $snapshot_id;
			}

			/**
			 * ✅ Хук: можна підʼєднати Preview/лог/аналітику
			 */
			do_action('seojusai/tasks/approved', $task);

			foreach ( $executors as $executor ) {
				if ( $executor->supports($type) ) {

					/**
					 * ✅ Хук: перед фактичним apply
					 */
					do_action('seojusai/tasks/pre_apply', $task);

					$executor->apply($task);

					/**
					 * ✅ Хук: після apply
					 */
					do_action('seojusai/tasks/applied', $task);

					break;
				}
			}
		}

		delete_option('seojusai_pending_tasks');
	}
}
