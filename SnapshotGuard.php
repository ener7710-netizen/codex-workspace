<?php
declare(strict_types=1);

namespace SEOJusAI\Executors;

use SEOJusAI\Snapshots\SnapshotService;

defined('ABSPATH') || exit;

final class SnapshotGuard {

	private SnapshotService $snapshots;

	public function __construct(?SnapshotService $snapshots = null) {
		$this->snapshots = $snapshots ?? new SnapshotService();
	}

	public function rollback(int $snapshot_id): bool {
		$result = $this->snapshots->restore_post_snapshot($snapshot_id);
		return $result === true;
	}
}
