<?php
declare(strict_types=1);

namespace SEOJusAI\Snapshots;

use wpdb;

defined('ABSPATH') || exit;

/**
 * SnapshotRepository
 * Відповідає за фізичне збереження та отримання зрізів даних із БД.
 */
final class SnapshotRepository {

	private string $table;
	private ?bool $table_exists = null;

	public function __construct(?wpdb $db = null) {
		global $wpdb;
		$db = $db instanceof wpdb ? $db : $wpdb;
		$this->table = $db->prefix . 'seojusai_snapshots';
	}

	/**
	 * Перевірка існування таблиці.
	 */
	public function exists(): bool {
		if ($this->table_exists !== null) {
			return $this->table_exists;
		}

		$cache_key = 'table_exists_' . md5($this->table);
		$cached = wp_cache_get($cache_key, 'seojusai');
		if (is_bool($cached)) {
			$this->table_exists = $cached;
			return $cached;
		}

		global $wpdb;
		$found = $wpdb->get_var(
			$wpdb->prepare('SHOW TABLES LIKE %s', $this->table)
		);
		$ok = (string) $found === $this->table;

		$this->table_exists = $ok;
		wp_cache_set($cache_key, $ok, 'seojusai', 3600);
		return $ok;
	}

	/**
	 * Отримання одного снапшота за ID.
	 */
	public function get(int $id): ?array {
		global $wpdb;

		if (!$this->exists() || $id <= 0) {
			return null;
		}

		$row = $wpdb->get_row(
			$wpdb->prepare("SELECT * FROM {$this->table} WHERE id = %d", $id),
			ARRAY_A
		);

		if (!$row) {
			return null;
		}

		$row['data'] = json_decode((string) $row['data_json'], true);
		return $row;
	}

	/**
	 * Збереження снапшота (GSC / PageSpeed / Post / SERP).
	 */
	public function insert(string $entity_type, int $entity_id, array $data): int {
		global $wpdb;

		if (!$this->exists()) {
			return 0;
		}

		$entity_type = sanitize_key($entity_type);
		if ($entity_type === '' || $entity_id <= 0) {
			return 0;
		}

		$json = wp_json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
		if (!$json) {
			return 0;
		}

		$ok = $wpdb->insert(
			$this->table,
			[
				'type'      => $entity_type, // Згідно з нашою схемою Tables.php
				'site_id'   => (int) crc32(get_home_url()), // Для адвокатських мереж
				'post_id'   => $entity_type === 'post' ? $entity_id : 0,
				'data_json' => $json,
				'hash'      => md5($json),
				'created_at' => current_time('mysql', true),
			],
			['%s', '%d', '%d', '%s', '%s', '%s']
		);

		return $ok ? (int) $wpdb->insert_id : 0;
	}

	/**
	 * Отримання останніх снапшотів для Gemini аналізу.
	 */
	public function get_latest(string $entity_type, int $entity_id, int $limit = 5): array {
		global $wpdb;

		if (!$this->exists()) {
			return [];
		}

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT data_json, created_at
				 FROM {$this->table}
				 WHERE type = %s AND (post_id = %d OR site_id = %d)
				 ORDER BY id DESC
				 LIMIT %d",
				sanitize_key($entity_type),
				$entity_id,
				$entity_id,
				max(1, $limit)
			),
			ARRAY_A
		);

		if (!is_array($rows)) {
			return [];
		}

		$out = [];
		foreach ($rows as $r) {
			$data = json_decode((string) $r['data_json'], true);
			if (is_array($data)) {
				$out[] = [
					'data' => $data,
					'at'   => $r['created_at'],
				];
			}
		}

		return $out;
	}

	/**
	 * Отримати останній snapshot за типом (без прив'язки до конкретного entity).
	 *
	 * Важливо: використовується для "об'єктивних" датасетів (GSC/GA4),
	 * щоб AI працював лише на зафіксованих фактах.
	 *
	 * @return array<string,mixed>|null
	 */
	public function get_latest_by_type(string $type): ?array {
		global $wpdb;
		if (!$this->exists()) {
			return null;
		}
		$type = sanitize_key($type);
		if ($type === '') {
			return null;
		}
		$row = $wpdb->get_row(
			$wpdb->prepare("SELECT id, data_json, created_at FROM {$this->table} WHERE type = %s ORDER BY id DESC LIMIT 1", $type),
			ARRAY_A
		);
		if (!is_array($row) || empty($row['data_json'])) {
			return null;
		}
		$data = json_decode((string) $row['data_json'], true);
		if (!is_array($data)) {
			return null;
		}
		$data['_snapshot'] = [
			'id' => (int) ($row['id'] ?? 0),
			'created_at' => (string) ($row['created_at'] ?? ''),
		];
		return $data;
	}

/**
 * Отримати ID останнього snapshot для поста (type='post').
 */
public function get_latest_post_snapshot_id(int $post_id): int {
    $post_id = (int)$post_id;
    if ($post_id <= 0) return 0;

    global $wpdb;
    $id = (int) $wpdb->get_var($wpdb->prepare(
        "SELECT id FROM {$this->table} WHERE type = 'post' AND post_id = %d ORDER BY id DESC LIMIT 1",
        $post_id
    ));
    return $id > 0 ? $id : 0;
}

}
