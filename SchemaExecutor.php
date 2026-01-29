<?php
declare(strict_types=1);

namespace SEOJusAI\Executors;

defined('ABSPATH') || exit;

/**
 * SchemaExecutor
 *
 * Застосовує schema задачі.
 * Наразі делегує в ApplyService (якщо schema приходить як action),
 * або може бути розширено через хук seojusai/executor/add_schema.
 */
final class SchemaExecutor extends AbstractExecutor {

	private ApplyService $apply;

	public function __construct(?ApplyService $apply = null) {
		parent::__construct();
		$this->apply = $apply ?? new ApplyService();
	}

	public function supports(string $type): bool {
		return in_array($type, ['schema', 'add_schema', 'schema_update'], true);
	}

	public function apply(array $task): bool {

		// Якщо task описує конкретну schema-дію — дозволяємо існуючому SchemaApplyExecutor відпрацювати через action.
		if (isset($task['type']) && (string)$task['type'] === 'add_schema') {
			do_action('seojusai/executor/add_schema', $task);
			return true;
		}

		$decision = $task['decision'] ?? null;
		$post_id  = (int) ($task['post_id'] ?? 0);

		if (!is_array($decision) || $post_id <= 0) {
			return false;
		}

		$ctx = [
			'post_id'     => $post_id,
			'snapshot_id' => (int) ($task['snapshot_id'] ?? 0),
		];

		return $this->apply->apply($decision, $ctx);
	}
}
