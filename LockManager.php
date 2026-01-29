<?php
declare(strict_types=1);

namespace SEOJusAI\Locks;

use SEOJusAI\Core\EmergencyStop;
use wpdb;

if ( ! defined('ABSPATH') ) {
	exit;
}

/**
 * LockManager
 *
 * Менеджер блокувань (mutex) для SEOJusAI.
 * Захищає від паралельного виконання критичних операцій.
 */
final class LockManager {

	private string $table;

	public function __construct() {
		global $wpdb;
		/** @var wpdb $wpdb */
		$this->table = $wpdb->prefix . 'seojusai_locks';
	}

	/**
	 * Спроба отримати lock.
	 *
	 * @param string $name Назва lock (унікальна)
	 * @param int $ttl Секунди життя lock
	 */
	public function acquire(string $name, int $ttl = 300): bool {

		if ( EmergencyStop::is_active() ) {
			return false;
		}

		$name = sanitize_key($name);
		if ( $name === '' ) {
			return false;
		}

		global $wpdb;

		$now = time();
		$expires = $now + max(30, $ttl);

		// Видаляємо прострочені lockʼи
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$this->table} WHERE expires_at < %d",
				$now
			)
		);

		// Пробуємо вставити новий lock
		$inserted = $wpdb->insert(
			$this->table,
			[
				'lock_name'   => $name,
				'created_at' => $now,
				'expires_at' => $expires,
			],
			[ '%s', '%d', '%d' ]
		);

		return $inserted !== false;
	}

	/**
	 * Звільнити lock.
	 */
	public function release(string $name): bool {

		$name = sanitize_key($name);
		if ( $name === '' ) {
			return false;
		}

		global $wpdb;

		return (bool) $wpdb->delete(
			$this->table,
			[ 'lock_name' => $name ],
			[ '%s' ]
		);
	}

	/**
	 * Перевірити, чи lock активний.
	 */
	public function is_locked(string $name): bool {

		$name = sanitize_key($name);
		if ( $name === '' ) {
			return false;
		}

		global $wpdb;

		$now = time();

		$count = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(1) FROM {$this->table}
				 WHERE lock_name = %s AND expires_at >= %d",
				$name,
				$now
			)
		);

		return $count > 0;
	}

	/**
	 * Force-cleanup (використовується CleanupController).
	 */
	public function cleanup(): int {

		global $wpdb;

		$now = time();

		return (int) $wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$this->table} WHERE expires_at < %d",
				$now
			)
		);
	}
}
