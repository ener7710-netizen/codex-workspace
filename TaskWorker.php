<?php
declare(strict_types=1);

namespace SEOJusAI\Tasks;

use SEOJusAI\Core\EmergencyStop;

defined('ABSPATH') || exit;

final class TaskWorker {

	private TaskQueue $queue;
	private bool $running = false;

	public function __construct(?TaskQueue $queue = null) {
		$this->queue = $queue ?? new TaskQueue();
	}

	public function run_once(): void {
		if ( $this->running || EmergencyStop::is_active() ) return;

		$this->running = true;
		try {
			$task = $this->queue->reserve_next();
			if ( ! $task ) return;

			$ok = $this->dispatch($task);
			$ok ? $this->queue->complete((int)$task['id']) : $this->queue->fail((int)$task['id'], (string)($task['_last_error'] ?? ''));
		} finally {
			$this->running = false;
		}
	}

	private function dispatch(array &$task): bool {
		$type    = (string) ($task['action'] ?? '');
		$payload = (array)  ($task['payload'] ?? []);

		try {
			unset($task['_last_error']);
			return (bool) apply_filters('seojusai/tasks/execute', false, $type, $payload, $task);
		} catch ( \Throwable $e ) {
			$task['_last_error'] = $e->getMessage();
			return false;
		}
	}
}
