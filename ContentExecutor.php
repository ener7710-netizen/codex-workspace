<?php
declare(strict_types=1);

namespace SEOJusAI\Executors;

defined('ABSPATH') || exit;

/**
 * ContentExecutor
 *
 * Human-in-the-Loop executor: застосовує зміни контенту/заголовків/уривків.
 * Працює через ApplyService (який перевіряє snapshot_id та контракт).
 */
final class ContentExecutor extends AbstractExecutor {

	private ApplyService $apply;

	public function __construct(?ApplyService $apply = null) {
		parent::__construct();
		$this->apply = $apply ?? new ApplyService();
	}

	public function supports(string $type): bool {
		return in_array($type, ['content', 'content_update', 'content_replace'], true);
	}

	public function apply(array $task): bool {

		$decision = $task['decision'] ?? null;
		$post_id  = (int) ($task['post_id'] ?? 0);

		if (!is_array($decision) || $post_id <= 0) {
			return false;
		}

		$ctx = [
			'post_id'      => $post_id,
			'snapshot_id'  => (int) ($task['snapshot_id'] ?? 0),
		];

		return $this->apply->apply($decision, $ctx);
	}
}
