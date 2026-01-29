<?php
declare(strict_types=1);

namespace SEOJusAI\Tasks;

defined('ABSPATH') || exit;

/**
 * TaskHooks
 * ЄДИНА точка звʼязку: Autopilot / AI → TaskQueue
 */
final class TaskHooks {

	public static function register(): void {
		add_action(
			'seojusai/tasks/enqueue',
			static function (array $tasks): void {
				if (empty($tasks)) return;

				$queue = new TaskQueue();

				foreach ($tasks as $task) {
					if (empty($task['action']) || empty($task['post_id'])) continue;

					// Формуємо унікальний ключ, щоб не дублювати однакові поради AI
					$key = sprintf(
						'%s_%d_%s',
						$task['action'],
						(int) $task['post_id'],
						md5((string)wp_json_encode($task))
					);

					$queue->enqueue(
						(string) $task['action'],
						$task,
						$key
					);
				}
			},
			10,
			1
		);
	}
}
