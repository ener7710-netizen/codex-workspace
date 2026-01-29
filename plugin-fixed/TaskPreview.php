<?php
declare(strict_types=1);

namespace SEOJusAI\Admin\Tasks;

use SEOJusAI\Snapshots\SnapshotService;
use SEOJusAI\Snapshots\DiffService;

defined('ABSPATH') || exit;

final class TaskPreview {

	public static function render(array $task): void {

		$post_id = (int) ($task['post_id'] ?? 0);
		$snapshot_id = $task['snapshot_id'] ?? null;

		if (!$post_id || !$snapshot_id) {
			echo '<p>Preview unavailable</p>';
			return;
		}

		$snapshots = new SnapshotService();
		$diffs     = new DiffService();

		$before = $snapshots->get_snapshot($snapshot_id);
		$after  = get_post_field('post_content', $post_id);

		if (!$before) {
			echo '<p>Snapshot not found</p>';
			return;
		}

		$diff = $diffs->diff((string)$before['content'], (string)$after);

		echo '<h3>Попередній перегляд змін</h3>';
		echo '<pre style="background:#111;color:#eee;padding:10px;">';

		foreach ($diff as $row) {

			if ($row['type'] === 'add') {
				echo "<span style='color:#4caf50'>+ {$row['line']}</span>\n";
			}

			if ($row['type'] === 'remove') {
				echo "<span style='color:#f44336'>- {$row['line']}</span>\n";
			}

			if ($row['type'] === 'change') {
				echo "<span style='color:#ff9800'>~ {$row['before']} → {$row['after']}</span>\n";
			}
		}

		echo '</pre>';
	}
}
