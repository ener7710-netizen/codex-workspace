<?php
declare(strict_types=1);

namespace SEOJusAI\KBE;

use wpdb;

if ( ! defined('ABSPATH') ) {
	exit;
}

/**
 * Repository
 *
 * DB-шар для Бази Знань (KBE).
 * Відповідає за збереження досвіду взаємодії користувача з ІІ.
 */
final class Repository {

	private wpdb $db;
	private string $table;

	public function __construct() {
		global $wpdb;
		$this->db    = $wpdb;
		$this->table = $wpdb->prefix . 'seojusai_knowledge';
	}

	/**
	 * Вставка або оновлення правила.
	 * Використовує ON DUPLICATE KEY UPDATE для накопичення ваги помилок.
	 */
	public function upsert(array $data): void {
		$sql = $this->db->prepare(
			"INSERT INTO {$this->table} (context_hash, rule_key, rule_value, error_weight)
			 VALUES (%s, %s, %s, %d)
			 ON DUPLICATE KEY UPDATE
			 error_weight = error_weight + VALUES(error_weight),
			 rule_value = VALUES(rule_value)",
			$data['context_hash'],
			$data['rule_key'],
			$data['rule_value'],
			$data['error_weight']
		);

		$this->db->query($sql);
	}

	/**
	 * Отримати правила для конкретного контексту (наприклад, для post_type 'lawyer_service').
	 */
	public function get_by_context(string $context_hash): array {
		$sql = $this->db->prepare(
			"SELECT rule_key, rule_value, error_weight FROM {$this->table} WHERE context_hash = %s ORDER BY error_weight DESC",
			$context_hash
		);

		return (array) $this->db->get_results($sql, ARRAY_A);
	}

	/**
	 * Отримати останні записи для глобального навчання.
	 */
	public function get_recent(int $limit = 50): array {
		$limit = max(1, min(200, $limit));
		$sql = "SELECT * FROM {$this->table} ORDER BY id DESC LIMIT " . (int) $limit;

		return (array) $this->db->get_results($sql, ARRAY_A);
	}
}
