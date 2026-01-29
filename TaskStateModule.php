<?php
declare(strict_types=1);

namespace SEOJusAI\Modules;

use SEOJusAI\Core\Contracts\ModuleInterface;
use SEOJusAI\Core\Kernel;

defined('ABSPATH') || exit;

final class TaskStateModule implements ModuleInterface {

	public function get_slug(): string {
		return 'tasks';
	}

	public function register(Kernel $kernel): void {
		$kernel->register_module($this->get_slug(), $this);
	}

	public function init(Kernel $kernel): void {

		// Создание задачи
		add_action('seojusai/tasks/enqueue', function (array $task) {
			$task['status']     = 'pending';
			$task['created_at'] = time();
			$tasks = get_option('seojusai_tasks', []);
			$tasks[] = $task;
			update_option('seojusai_tasks', $tasks, false);
		});

		// Подтверждение
		add_action('seojusai/tasks/approved', function (array $task) {
			$this->update_status($task, 'approved');
		});

		// Применение
		add_action('seojusai/executor/success', function (array $payload) {
			$this->update_by_post(
				(int) ($payload['post_id'] ?? 0),
				'applied'
			);
		});

		// Ошибка
		add_action('seojusai/executor/error', function (array $payload) {
			$this->update_by_post(
				(int) ($payload['post_id'] ?? 0),
				'failed'
			);
		});
	}

	private function update_status(array $task, string $status): void {
		$tasks = get_option('seojusai_tasks', []);
		foreach ($tasks as &$t) {
			if (($t['decision_hash'] ?? '') === ($task['decision_hash'] ?? '')) {
				$t['status'] = $status;
				$t['updated_at'] = time();
			}
		}
		update_option('seojusai_tasks', $tasks, false);
	}

	private function update_by_post(int $post_id, string $status): void {
		if ($post_id <= 0) return;

		$tasks = get_option('seojusai_tasks', []);
		foreach ($tasks as &$t) {
			if ((int)($t['post_id'] ?? 0) === $post_id) {
				$t['status'] = $status;
				$t['updated_at'] = time();
			}
		}
		update_option('seojusai_tasks', $tasks, false);
	}
}
