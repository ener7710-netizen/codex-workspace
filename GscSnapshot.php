<?php
declare(strict_types=1);

namespace SEOJusAI\GSC;

use SEOJusAI\Snapshots\SnapshotRepository;

defined('ABSPATH') || exit;

final class GscSnapshot {

	public static function save(string $site, array $data): void {

		$site_id = (int) crc32($site);

		$snapshots = new SnapshotRepository();
		$snapshots->insert(
			'gsc',
			$site_id,
			[
				'site' => $site,
				'data' => $data,
			]
		);
	}

	/**
	 * Повернути історію GSC-снапшотів за останні N днів.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	public static function get_history(int $days = 30): array {
		$days = max(1, min(365, (int) $days));
		$since = gmdate('Y-m-d H:i:s', time() - ($days * DAY_IN_SECONDS));
		$snapshots = new SnapshotRepository();
		if (!$snapshots->exists()) {
			return [];
		}
		global $wpdb;
		$table = $wpdb->prefix . 'seojusai_snapshots';
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id, data_json, created_at FROM {$table} WHERE type = 'gsc' AND created_at >= %s ORDER BY id DESC LIMIT 200",
				$since
			),
			ARRAY_A
		);
		return is_array($rows) ? $rows : [];
	}

	/**
	 * Очистити історію GSC-снапшотів.
	 */
	public static function clear(): void {
		$snapshots = new SnapshotRepository();
		if (!$snapshots->exists()) {
			return;
		}
		global $wpdb;
		$table = $wpdb->prefix . 'seojusai_snapshots';
		$wpdb->query("DELETE FROM {$table} WHERE type = 'gsc'");
	}
}
