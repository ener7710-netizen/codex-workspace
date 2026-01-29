<?php
declare(strict_types=1);

namespace SEOJusAI\Redirects;

use wpdb;

defined('ABSPATH') || exit;

final class RedirectRepository {

	private string $table;

	public function __construct(?wpdb $db = null) {
		global $wpdb;
		$db = $db instanceof wpdb ? $db : $wpdb;
		$this->table = $db->prefix . 'seojusai_redirects';
	}

	public function exists(): bool {
		global $wpdb;
		return $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $this->table)) === $this->table;
	}

	public function upsert(string $from_path, string $to_url, int $code = 301): bool {
		global $wpdb;
		$from_path = $this->normalize_path($from_path);
		$to_url = esc_url_raw($to_url);
		$code = in_array($code, [301,302,307,308], true) ? $code : 301;
		if ($from_path === '' || $to_url === '') {
			return false;
		}

		$row = $wpdb->get_row($wpdb->prepare("SELECT id FROM {$this->table} WHERE from_path=%s", $from_path), ARRAY_A);
		if ($row) {
			return (bool) $wpdb->update($this->table, [
				'to_url' => $to_url,
				'code' => $code,
				'updated_at' => current_time('mysql'),
			], ['id' => (int)$row['id']]);
		}
		return (bool) $wpdb->insert($this->table, [
			'from_path' => $from_path,
			'to_url' => $to_url,
			'code' => $code,
			'hits' => 0,
			'created_at' => current_time('mysql'),
			'updated_at' => current_time('mysql'),
		]);
	}

	public function delete(int $id): void {
		global $wpdb;
		$wpdb->delete($this->table, ['id' => $id]);
	}

	public function match(string $request_path): ?array {
		global $wpdb;
		$request_path = $this->normalize_path($request_path);
		if ($request_path === '') {
			return null;
		}
		$row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$this->table} WHERE from_path=%s", $request_path), ARRAY_A);
		return $row ?: null;
	}

	public function bump_hit(int $id): void {
		global $wpdb;
		$wpdb->query($wpdb->prepare("UPDATE {$this->table} SET hits = hits + 1, updated_at=%s WHERE id=%d", current_time('mysql'), $id));
	}

	public function all(int $limit = 200): array {
		global $wpdb;
		return (array) $wpdb->get_results($wpdb->prepare("SELECT * FROM {$this->table} ORDER BY updated_at DESC LIMIT %d", max(1,$limit)), ARRAY_A);
	}

	private function normalize_path(string $path): string {
		$path = trim($path);
		if ($path === '') return '';
		// allow full URL: take path part
		if (preg_match('~^https?://~i', $path)) {
			$parts = wp_parse_url($path);
			$path = (string)($parts['path'] ?? '/');
			$query = isset($parts['query']) ? ('?' . $parts['query']) : '';
			$path .= $query;
		}
		if ($path[0] !== '/') $path = '/' . $path;
		return $path;
	}
}
