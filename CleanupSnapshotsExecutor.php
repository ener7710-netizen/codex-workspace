<?php
declare(strict_types=1);

namespace SEOJusAI\Executors;

use SEOJusAI\Snapshots\SnapshotRepository;

defined('ABSPATH') || exit;

/**
 * CleanupSnapshotsExecutor
 * Відповідає за ротацію снапшотів, щоб таблиця не розросталася до критичних розмірів.
 */
final class CleanupSnapshotsExecutor {

	public function register(): void {
		// Може викликатися через WP-Cron або після успішного аналізу
		add_action(
			'seojusai/executor/cleanup_snapshots',
			[$this, 'handle'],
			10,
			1
		);
	}

	/**
	 * Видаляє старі снапшоти, залишаючи лише $keep останніх.
	 *
	 * @param array{site: string, keep: int} $params
	 */
	public function handle(array $params): void {
		$site = (string) ($params['site'] ?? get_home_url());
		$keep = (int) ($params['keep'] ?? 10);

		if ($keep <= 0) {
			return;
		}

		$site_id = (int) crc32($site);
		$repo = new SnapshotRepository();

		/**
		 * Ми проходимо по всіх основних типах даних.
		 * Примітка: Ми НЕ видаляємо тип 'post', оскільки це історія для Undo.
		 */
		foreach (['pagespeed', 'gsc', 'serp'] as $type) {
			$this->cleanup_by_type($repo, $type, $site_id, $keep);
		}
	}

	private function cleanup_by_type(SnapshotRepository $repo, string $type, int $site_id, int $keep): void {
		global $wpdb;
		$table = $wpdb->prefix . 'seojusai_snapshots';

		// Отримуємо ID снапшотів, які потрібно видалити (все, що не входить в TOP $keep)
		$ids_to_delete = $wpdb->get_col($wpdb->prepare(
			"SELECT id FROM {$table}
			 WHERE type = %s AND site_id = %d
			 ORDER BY id DESC LIMIT 1000 OFFSET %d",
			$type,
			$site_id,
			$keep
		));

		if (!empty($ids_to_delete)) {
			$ids_string = implode(',', array_map('intval', $ids_to_delete));
			$wpdb->query("DELETE FROM {$table} WHERE id IN ($ids_string)");
		}
	}
}
