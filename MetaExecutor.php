<?php
declare(strict_types=1);

namespace SEOJusAI\Executors;

defined('ABSPATH') || exit;

/**
 * MetaExecutor
 *
 * Застосовує SEO-метадані (title/description/canonical тощо) через ApplyService.
 */
final class MetaExecutor extends AbstractExecutor {

	private ApplyService $apply;

	public function __construct(?ApplyService $apply = null) {
		parent::__construct();
		$this->apply = $apply ?? new ApplyService();
	}

	public function supports(string $type): bool {
		return in_array($type, ['meta', 'meta_update'], true);
	}

	public function apply(array $task): bool {

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
