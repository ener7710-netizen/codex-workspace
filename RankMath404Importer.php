<?php
declare(strict_types=1);

namespace SEOJusAI\Redirects;

use wpdb;

defined('ABSPATH') || exit;

/**
 * Best-effort importer for Rank Math 404 logs.
 *
 * This class does not assume a specific Rank Math schema.
 * It tries to detect a suitable table and columns at runtime.
 */
final class RankMath404Importer {

	private wpdb $db;

	public function __construct(?wpdb $db = null) {
		global $wpdb;
		$this->db = $db instanceof wpdb ? $db : $wpdb;
	}

	public function is_available(): bool {
		return $this->detect_table_name() !== '';
	}

	/**
	 * @return array<int, array{url:string,hits?:int,referrer?:string,last_seen?:string,first_seen?:string}>
	 */
	public function fetch_rows(int $limit = 500): array {
		$table = $this->detect_table_name();
		if ($table === '') {
			return [];
		}

		$columns = $this->get_columns($table);
		if (empty($columns)) {
			return [];
		}

		$url_col = $this->pick_column($columns, ['url', 'uri', 'request', 'request_uri']);
		if ($url_col === '') {
			return [];
		}
		$hits_col = $this->pick_column($columns, ['hits', 'count', 'access_count', 'hit']);
		$ref_col  = $this->pick_column($columns, ['referrer', 'referer', 'ref']);
		$last_col = $this->pick_column($columns, ['last_accessed', 'last_seen', 'accessed', 'updated_at', 'last_hit']);
		$first_col = $this->pick_column($columns, ['created_at', 'first_seen', 'first_accessed', 'first_hit']);

		$select = [$url_col . ' AS url'];
		if ($hits_col !== '') {
			$select[] = $hits_col . ' AS hits';
		}
		if ($ref_col !== '') {
			$select[] = $ref_col . ' AS referrer';
		}
		if ($first_col !== '') {
			$select[] = $first_col . ' AS first_seen';
		}
		if ($last_col !== '') {
			$select[] = $last_col . ' AS last_seen';
		}

		$sql = 'SELECT ' . implode(', ', $select) . " FROM {$table}";
		if ($hits_col !== '') {
			$sql .= " ORDER BY {$hits_col} DESC";
		} elseif ($last_col !== '') {
			$sql .= " ORDER BY {$last_col} DESC";
		}
		$sql .= $this->db->prepare(' LIMIT %d', max(1, $limit));

		$rows = $this->db->get_results($sql, ARRAY_A);
		if (!is_array($rows)) {
			return [];
		}

		$out = [];
		foreach ($rows as $row) {
			if (!is_array($row) || !isset($row['url'])) {
				continue;
			}
			$url = (string) $row['url'];
			if ($url === '') {
				continue;
			}
			$out[] = [
				'url' => $url,
				'hits' => isset($row['hits']) ? (int) $row['hits'] : 0,
				'referrer' => isset($row['referrer']) ? (string) $row['referrer'] : '',
				'first_seen' => isset($row['first_seen']) ? (string) $row['first_seen'] : '',
				'last_seen' => isset($row['last_seen']) ? (string) $row['last_seen'] : '',
			];
		}

		return $out;
	}

	private function detect_table_name(): string {
		// Common Rank Math variants.
		$candidates = [
			$this->db->prefix . 'rank_math_404_logs',
			$this->db->prefix . 'rank_math_404_log',
			$this->db->prefix . 'rank_math_404',
		];

		foreach ($candidates as $table) {
			if ($this->table_exists($table)) {
				return $table;
			}
		}

		// Fallback: discover via LIKE.
		$like = $this->db->esc_like($this->db->prefix . 'rank_math_') . '%';
		$tables = $this->db->get_col($this->db->prepare('SHOW TABLES LIKE %s', $like));
		if (!is_array($tables) || empty($tables)) {
			return '';
		}
		foreach ($tables as $table) {
			if (!is_string($table)) {
				continue;
			}
			if (str_contains($table, '404') && $this->table_exists($table)) {
				return $table;
			}
		}

		return '';
	}

	private function table_exists(string $table): bool {
		$found = $this->db->get_var($this->db->prepare('SHOW TABLES LIKE %s', $table));
		return is_string($found) && $found === $table;
	}

	/**
	 * @return array<int, string>
	 */
	private function get_columns(string $table): array {
		$cols = $this->db->get_col("SHOW COLUMNS FROM {$table}");
		if (!is_array($cols)) {
			return [];
		}
		$out = [];
		foreach ($cols as $c) {
			if (is_string($c) && $c !== '') {
				$out[] = $c;
			}
		}
		return $out;
	}

	/**
	 * @param array<int, string> $columns
	 * @param array<int, string> $preferred
	 */
	private function pick_column(array $columns, array $preferred): string {
		$map = [];
		foreach ($columns as $col) {
			$map[strtolower($col)] = $col;
		}
		foreach ($preferred as $p) {
			$k = strtolower($p);
			if (isset($map[$k])) {
				return $map[$k];
			}
		}
		return '';
	}
}
