<?php
declare(strict_types=1);

namespace SEOJusAI\Admin\Tasks;

use SEOJusAI\Executors\ExecutorResolver;
use SEOJusAI\Snapshots\SnapshotService;
use SEOJusAI\Decisions\DecisionRepository;
use SEOJusAI\Input\Input;

defined('ABSPATH') || exit;

final class TasksPage {

	public function render(): void {

		if ( ! current_user_can('manage_options') ) {
			return;
		}

		$this->handle_actions();

		$table = new TaskListTable();
		$table->prepare_items();

		echo '<div class="wrap">';
		echo '<h1>SEOJusAI — Завдання</h1>';

		echo '<form method="post">';
		wp_nonce_field('seojusai_tasks');

		$table->display();

		echo '</form>';
		echo '</div>';
	}

	private function handle_actions(): void {

		if ( empty(Input::post('_wpnonce')) || ! wp_verify_nonce(Input::post('_wpnonce'), 'seojusai_tasks') ) {
			return;
		}

		$action = Input::post('action') ?? '';
		$ids    = (array) (Input::post('task_ids') ?? []);

		if ( empty($action) || empty($ids) ) {
			return;
		}

		$tasks = get_option('seojusai_tasks', []);
		if ( ! is_array($tasks) ) {
			return;
		}

		foreach ( $tasks as &$task ) {

			if ( ! in_array($task['decision_hash'] ?? '', $ids, true) ) {
				continue;
			}

			if ( $action === 'approve' ) {
				$task['status'] = 'approved';
				do_action('seojusai/tasks/approved', $task);
			}

			if ( $action === 'apply' ) {

				$snapshot_id = (new SnapshotService())
					->capture_post((int) $task['post_id'], 'admin_apply');

				$task['snapshot_id'] = $snapshot_id;
				$task['status']      = 'applied';

				// 🧠 Decision ядро
				if (class_exists(DecisionRepository::class)) {
					$task['decision_record_id'] = (new DecisionRepository())->create((array)($task['decision'] ?? []), [
						'post_id' => (int)($task['post_id'] ?? 0),
						'source' => 'admin_tasks',
						'context_type' => 'page',
						'meta' => ['user_id' => get_current_user_id()],
					]);
					do_action('seojusai/decision/created', ['decision_id' => (int)$task['decision_record_id'], 'post_id' => (int)($task['post_id'] ?? 0), 'source' => 'admin_tasks']);
				}

				do_action('seojusai/executor/run_task', $task);
			}

			if ( $action === 'delete' ) {
				$task['status'] = 'deleted';
			}
		}

		update_option('seojusai_tasks', $tasks, false);
	}
}
