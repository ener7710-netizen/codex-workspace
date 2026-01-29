<?php
declare(strict_types=1);

namespace SEOJusAI\Executors;

use SEOJusAI\Snapshots\SnapshotService;

defined('ABSPATH') || exit;

abstract class AbstractExecutor implements ExecutorInterface {

	protected SnapshotService $snapshots;

	public function __construct(?SnapshotService $snapshots = null) {
		$this->snapshots = $snapshots ?? new SnapshotService();
	}

	protected function snapshot(int $post_id, string $label, array $context = []): void {
		$this->snapshots->capture_post($post_id, $label, $context);
	}
}
