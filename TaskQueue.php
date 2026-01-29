<?php
declare(strict_types=1);

namespace SEOJusAI\Tasks;

use SEOJusAI\Core\EmergencyStop;
use wpdb;

defined('ABSPATH') || exit;

final class TaskQueue {

	public const MAX_ATTEMPTS = 5;
	private string $table;

	public function __construct() {
		global $wpdb;
		$this->table = $wpdb->prefix . 'seojusai_tasks';
	}

	public function enqueue(string $type, array $payload, string $key): bool {
		if ( EmergencyStop::is_active() ) return false;

		$post_id   = isset($payload['post_id']) ? (int) $payload['post_id'] : 0;
		$hash_base = $post_id > 0 ? ($type . '|' . $post_id) : ($type . '|' . $key);
		$task_hash = md5($hash_base);
		$tr_key    = 'seojusai_task_' . $task_hash;
		if (get_transient($tr_key)) {
			return false;
		}
		set_transient($tr_key, 1, 15 * MINUTE_IN_SECONDS);

		global $wpdb;
		$exists = (int) $wpdb->get_var($wpdb->prepare(
			"SELECT COUNT(1) FROM {$this->table} WHERE decision_hash = %s",
			$key
		));

		if ( $exists > 0 ) return true;

		return $wpdb->insert(
			$this->table,
			[
				'action'        => sanitize_key($type),
				'post_id'       => (int) ($payload['post_id'] ?? 0),
				'decision_hash' => $key,
				'payload'       => (string) wp_json_encode($payload),
				'attempts'      => 0,
				'available_at'  => current_time('mysql'),
				'updated_at'    => current_time('mysql'),
				'status'        => 'pending',
				'priority'      => $payload['priority'] ?? 'medium',
				'created_at'    => current_time('mysql'),
			],
			['%s','%d','%s','%s','%s','%s','%s']
		) !== false;
	}

	public function reserve_next(): ?array {
		if ( EmergencyStop::is_active() ) return null;

		global $wpdb;
		$wpdb->query('START TRANSACTION');

		$row = $wpdb->get_row(
			"SELECT * FROM {$this->table} WHERE status = 'pending' AND (available_at IS NULL OR available_at <= NOW()) ORDER BY id ASC LIMIT 1 FOR UPDATE",
			ARRAY_A
		);

		if ( ! $row ) {
			$wpdb->query('COMMIT');
			return null;
		}

		$wpdb->update($this->table, ['status' => 'running','updated_at'=>current_time('mysql')], ['id' => $row['id']]);
		$wpdb->query('COMMIT');

		$row['payload'] = json_decode((string) $row['payload'], true);
		return $row;
	}

	public function complete(int $id): bool {
		global $wpdb;
		return $wpdb->update($this->table, [
			'status' => 'executed',
			'executed_at' => current_time('mysql'),
			'updated_at' => current_time('mysql')
		], ['id' => $id]) !== false;
	}

	public function fail(int $id, string $error = ''): bool {
		global $wpdb;

		$row = $wpdb->get_row($wpdb->prepare("SELECT attempts FROM {$this->table} WHERE id = %d", $id), ARRAY_A);
		$attempts = (int) ($row['attempts'] ?? 0);
		$attempts++;

		// If attempts remain — reschedule with exponential backoff
		if ($attempts < self::MAX_ATTEMPTS && class_exists(\SEOJusAI\Tasks\RetryPolicy::class)) {
			$delay = \SEOJusAI\Tasks\RetryPolicy::next_delay($attempts);
			$available = gmdate('Y-m-d H:i:s', time() + $delay);

			return $wpdb->update($this->table, [
				'status'       => 'pending',
				'attempts'     => $attempts,
				'available_at' => $available,
				'last_error'   => $error ? substr($error, 0, 1000) : null,
				'updated_at'   => current_time('mysql'),
			], ['id' => $id]) !== false;
		}

		// Exhausted attempts
		return $wpdb->update($this->table, [
			'status'     => 'failed',
			'attempts'   => $attempts,
			'last_error' => $error ? substr($error, 0, 1000) : null,
			'updated_at' => current_time('mysql'),
		], ['id' => $id]) !== false;
	}

	/**
	 * Повертає список задач для адмін-інтерфейсу.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	public function list(int $limit = 50, int $offset = 0, string $status = ''): array {
		global $wpdb;

		$limit = max(1, min(200, (int) $limit));
		$offset = max(0, (int) $offset);
		$status = sanitize_key($status);

		if ($status !== '') {
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT * FROM {$this->table} WHERE status = %s ORDER BY id DESC LIMIT %d OFFSET %d",
					$status,
					$limit,
					$offset
				),
				ARRAY_A
			);
		} else {
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT * FROM {$this->table} ORDER BY id DESC LIMIT %d OFFSET %d",
					$limit,
					$offset
				),
				ARRAY_A
			);
		}

		if (!$rows) {
			return [];
		}

		foreach ($rows as &$r) {
			$r['id'] = (int) ($r['id'] ?? 0);
			$r['post_id'] = (int) ($r['post_id'] ?? 0);
			$r['attempts'] = (int) ($r['attempts'] ?? 0);
			$r['payload'] = isset($r['payload']) && is_string($r['payload']) && $r['payload'] !== ''
				? json_decode((string) $r['payload'], true)
				: [];
			if (!is_array($r['payload'])) {
				$r['payload'] = [];
			}
		}

		return $rows;
	}

	/**
	 * Повторний запуск задачі: повертає її у pending та робить доступною "зараз".
	 */
	public function retry_now(int $id): bool {
		$id = (int) $id;
		if ($id <= 0) {
			return false;
		}
		global $wpdb;
		return $wpdb->update($this->table, [
			'status'       => 'pending',
			'available_at' => current_time('mysql'),
			'updated_at'   => current_time('mysql'),
		], ['id' => $id]) !== false;
	}

	/**
	 * Видалення задачі.
	 */
	public function delete(int $id): bool {
		$id = (int) $id;
		if ($id <= 0) {
			return false;
		}
		global $wpdb;
		return $wpdb->delete($this->table, ['id' => $id], ['%d']) !== false;
	}
}
