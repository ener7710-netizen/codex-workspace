<?php
declare(strict_types=1);

namespace SEOJusAI\SERP;

use SEOJusAI\Snapshots\SnapshotRepository;

defined('ABSPATH') || exit;

final class SerpSnapshot {

	public static function save(string $site, array $data): void {

		$site_id = (int) crc32($site);

		$snapshots = new SnapshotRepository();
		$snapshots->insert(
			'serp',
			$site_id,
			[
				'site' => $site,
				'data' => $data,
			]
		);
	}
}
