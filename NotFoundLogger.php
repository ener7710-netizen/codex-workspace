<?php
declare(strict_types=1);

namespace SEOJusAI\Redirects;

use wpdb;

defined('ABSPATH') || exit;

final class NotFoundLogger {

	private string $table;

	public function __construct(?wpdb $db = null) {
		global $wpdb;
		$db = $db instanceof wpdb ? $db : $wpdb;
		$this->table = $db->prefix . 'seojusai_404';
	}

	public function exists(): bool {
		global $wpdb;
		return $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $this->table)) === $this->table;
	}

	public function log(string $url, string $referrer = ''): void {
		global $wpdb;
		$url = esc_url_raw($url);
		$referrer = esc_url_raw($referrer);
		if ($url === '') return;

		$row = $wpdb->get_row($wpdb->prepare("SELECT id FROM {$this->table} WHERE url=%s", $url), ARRAY_A);
		if ($row) {
			$wpdb->query($wpdb->prepare("UPDATE {$this->table} SET hits = hits + 1, referrer=%s, last_seen=%s WHERE id=%d", $referrer, current_time('mysql'), (int)$row['id']));
			return;
		}
		$wpdb->insert($this->table, [
			'url' => $url,
			'referrer' => $referrer,
			'hits' => 1,
			'first_seen' => current_time('mysql'),
			'last_seen' => current_time('mysql'),
		]);
	}

	public function top(int $limit=100): array {
		global $wpdb;
		return (array) $wpdb->get_results($wpdb->prepare("SELECT * FROM {$this->table} ORDER BY hits DESC, last_seen DESC LIMIT %d", max(1,$limit)), ARRAY_A);
	}

	/**
	 * Імпортує 404-рядки у власну таблицю.
	 *
	 * @param array<int, array{url:string,hits?:int,referrer?:string,first_seen?:string,last_seen?:string}> $rows
	 */
	public function import_rows(array $rows): int {
		global $wpdb;
		$imported = 0;

		foreach ($rows as $row) {
			if (!is_array($row)) {
				continue;
			}

			$url = isset($row['url']) ? esc_url_raw((string) $row['url']) : '';
			if ($url === '') {
				continue;
			}

			$hits = isset($row['hits']) ? (int) $row['hits'] : 0;
			if ($hits < 0) {
				$hits = 0;
			}
			$ref = isset($row['referrer']) ? esc_url_raw((string) $row['referrer']) : '';
			$first = isset($row['first_seen']) ? sanitize_text_field((string) $row['first_seen']) : '';
			$last  = isset($row['last_seen']) ? sanitize_text_field((string) $row['last_seen']) : '';

			$existing = $wpdb->get_row($wpdb->prepare("SELECT id, hits FROM {$this->table} WHERE url=%s", $url), ARRAY_A);
			if (is_array($existing) && isset($existing['id'])) {
				$existing_hits = (int) ($existing['hits'] ?? 0);
				$new_hits = max($existing_hits, $hits);
				$wpdb->update(
					$this->table,
					[
						'hits' => $new_hits,
						'referrer' => $ref !== '' ? $ref : null,
						'last_seen' => $last !== '' ? $last : current_time('mysql'),
					],
					['id' => (int) $existing['id']]
				);
				$imported++;
				continue;
			}

			$ok = $wpdb->insert(
				$this->table,
				[
					'url' => $url,
					'referrer' => $ref,
					'hits' => max(1, $hits),
					'first_seen' => $first !== '' ? $first : current_time('mysql'),
					'last_seen' => $last !== '' ? $last : current_time('mysql'),
				]
			);

			if ($ok !== false) {
				$imported++;
			}
		}

		return $imported;
	}
}
