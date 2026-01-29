<?php
declare(strict_types=1);

namespace SEOJusAI\Executors;

use SEOJusAI\Core\EmergencyStop;
use SEOJusAI\AI\DecisionTypes;

defined('ABSPATH') || exit;

/**
 * ExecutorResolver
 *
 * Роль:
 * - приймає одну задачу (task array)
 * - визначає executor
 * - виконує (через action або напряму)
 *
 * ВАЖЛИВО:
 * - цей резолвер НЕ вирішує питання підтвердження.
 * - його треба викликати ТІЛЬКИ після approve/apply у UI.
 */
final class ExecutorResolver {

	private bool $registered = false;

	/**
	 * Карта: task[action] => executor hook
	 *
	 * @return array<string,string>
	 */
	private function map(): array {
		return [
			DecisionTypes::ADD_SCHEMA        => 'seojusai/executor/add_schema',
			DecisionTypes::CLEANUP_SNAPSHOTS => 'seojusai/executor/cleanup_snapshots',
		];
	}

	public function register(): void {
		if ( $this->registered ) {
			return;
		}
		$this->registered = true;

		/**
		 * ЄДИНА точка запуску executor-ів (після підтвердження)
		 *
		 * do_action('seojusai/executor/run_task', $task);
		 */
		add_action('seojusai/executor/run_task', [$this, 'run_task'], 10, 1);
	}

	/**
	 * @param array<string,mixed> $task
	 */
	public function run_task(array $task): void {

		if ( EmergencyStop::is_active() ) {
			return;
		}

		$action = isset($task['action']) ? sanitize_key((string) $task['action']) : '';
		if ( $action === '' ) {
			return;
		}

		$map = $this->map();

		// якщо немає мапінгу — просто вихід
		if ( empty($map[$action]) ) {
			do_action('seojusai/executor/unsupported', $task);
			return;
		}

		$hook = (string) $map[$action];

		/**
		 * Виконуємо через hook (гнучко, як у Rank Math)
		 * Конкретний executor підписується на свій hook.
		 */
		do_action($hook, $task);

		/**
		 * Unified event для логів/аудиту
		 */
		do_action('seojusai/executor/ran', [
			'action'    => $action,
			'post_id'   => (int) ($task['post_id'] ?? 0),
			'timestamp' => time(),
		]);
	}
}
