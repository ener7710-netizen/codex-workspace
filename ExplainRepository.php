<?php
declare(strict_types=1);

namespace SEOJusAI\Explain;

use wpdb;

defined('ABSPATH') || exit;

/**
 * ExplainRepository
 *
 * ЄДИНЕ джерело істини для запису/читання AI-пояснень та трасування рішень.
 * Таблиця: {$wpdb->prefix}seojusai_explanations
 */
final class ExplainRepository {

	private string $table;
	private wpdb $db;

	public function __construct(?wpdb $db = null) {
		global $wpdb;
		$this->db = $db instanceof wpdb ? $db : $wpdb;
		$this->table = $this->db->prefix . 'seojusai_explanations';
	}

	/**
	 * @param array<string,mixed> $explanation_struct
	 */
	public function save(
		string $entity_type,
		int $entity_id,
		string $decision_hash,
		array $explanation_struct,
		string $risk = 'low',
		string $source = 'ai',
		?string $model = null,
		?string $prompt = null,
		?string $response = null,
		int $tokens = 0
	): bool {
		$entity_type = sanitize_key($entity_type);
		$entity_id   = max(0, (int) $entity_id);
		$decision_hash = sanitize_text_field($decision_hash);

		if ($entity_type === '' || $entity_id < 0) {
			return false;
		}

		$explanation_json = wp_json_encode($explanation_struct, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
		if ($explanation_json === false) {
			$explanation_json = null;
		}

		$data = [
			'entity_type'   => $entity_type,
			'entity_id'     => $entity_id,
			'decision_hash' => $decision_hash,
			'model'         => $model ? sanitize_text_field($model) : null,
			'prompt'        => is_string($prompt) ? $prompt : null,
			'response'      => is_string($response) ? $response : null,
			'explanation'   => $explanation_json,
			'risk_level'    => sanitize_key($risk ?: 'low'),
			'source'        => sanitize_key($source ?: 'ai'),
			'tokens'        => max(0, (int) $tokens),
			'created_at'    => current_time('mysql', true),
		];

		$formats = ['%s','%d','%s','%s','%s','%s','%s','%s','%s','%d','%s'];

		$res = $this->db->insert($this->table, $data, $formats);

		if ($res !== 1) {
			do_action('seojusai/explain/db_error', [
				'error' => $this->db->last_error,
				'table' => $this->table,
			]);
		}

		return $res === 1;
	}

	/** @return array<int,array<string,mixed>> */
	public function list(string $entity_type, int $entity_id, int $limit = 10): array {
		$entity_type = sanitize_key($entity_type);
		$entity_id   = max(0, (int) $entity_id);
		$limit       = max(1, min(50, (int) $limit));

		$rows = $this->db->get_results(
			$this->db->prepare(
				"SELECT * FROM {$this->table}
				 WHERE entity_type = %s AND entity_id = %d
				 ORDER BY id DESC LIMIT %d",
				$entity_type,
				$entity_id,
				$limit
			),
			ARRAY_A
		);

		if (!$rows) {
			return [];
		}

		foreach ($rows as &$r) {
			$r['tokens'] = (int) ($r['tokens'] ?? 0);
			$r['explanation'] = isset($r['explanation']) && is_string($r['explanation']) && $r['explanation'] !== ''
				? json_decode((string) $r['explanation'], true)
				: null;
			if (!is_array($r['explanation'])) {
				$r['explanation'] = null;
			}
		}

		return $rows;
	}

	/**
	 * Отримати один запис за ID.
	 */
	public function get(int $id): ?array {
		$id = (int) $id;
		if ($id <= 0) {
			return null;
		}

		$row = $this->db->get_row(
			$this->db->prepare("SELECT * FROM {$this->table} WHERE id = %d", $id),
			ARRAY_A
		);
		if (!$row) {
			return null;
		}

		return $this->normalize_row($row);
	}

	/**
	 * Список Explain для адмін-центру (з фільтрами).
	 *
	 * @return array<int,array<string,mixed>>
	 */
	public function list_all(int $limit = 50, int $offset = 0, string $risk = '', string $entity_type = ''): array {
		$limit = max(1, min(200, (int) $limit));
		$offset = max(0, (int) $offset);
		$risk = sanitize_key($risk);
		$entity_type = sanitize_key($entity_type);

		$where = [];
		$args = [];

		if ($risk !== '') {
			$where[] = 'risk_level = %s';
			$args[] = $risk;
		}
		if ($entity_type !== '') {
			$where[] = 'entity_type = %s';
			$args[] = $entity_type;
		}

		$sql = "SELECT * FROM {$this->table}";
		if (!empty($where)) {
			$sql .= ' WHERE ' . implode(' AND ', $where);
		}
		$sql .= ' ORDER BY id DESC LIMIT %d OFFSET %d';
		$args[] = $limit;
		$args[] = $offset;

		$rows = $this->db->get_results(
			$this->db->prepare($sql, ...$args),
			ARRAY_A
		);
		if (!$rows) {
			return [];
		}

		$out = [];
		foreach ($rows as $r) {
			$out[] = $this->normalize_row($r);
		}
		return $out;
	}

	/**
	 * Список Explain за decision_hash.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	public function list_by_hash(string $decision_hash, int $limit = 50): array {
		$decision_hash = sanitize_text_field($decision_hash);
		$limit = max(1, min(200, (int) $limit));
		if ($decision_hash === '') {
			return [];
		}

		$rows = $this->db->get_results(
			$this->db->prepare(
				"SELECT * FROM {$this->table} WHERE decision_hash = %s ORDER BY id DESC LIMIT %d",
				$decision_hash,
				$limit
			),
			ARRAY_A
		);
		if (!$rows) {
			return [];
		}
		$out = [];
		foreach ($rows as $r) {
			$out[] = $this->normalize_row($r);
		}
		return $out;
	}

	/**
	 * Агрегація по сайту за N днів.
	 *
	 * @return array{total:int,avg_confidence:string,by_risk:array<string,int>}
	 */
	public function aggregates_site(int $days = 30): array {
		$days = max(1, min(365, (int) $days));
		$since = gmdate('Y-m-d H:i:s', time() - ($days * DAY_IN_SECONDS));

		$total = (int) $this->db->get_var(
			$this->db->prepare(
				"SELECT COUNT(1) FROM {$this->table} WHERE created_at >= %s",
				$since
			)
		);

		$by = $this->db->get_results(
			$this->db->prepare(
				"SELECT risk_level, COUNT(1) c FROM {$this->table} WHERE created_at >= %s GROUP BY risk_level",
				$since
			),
			ARRAY_A
		);
		$by_risk = [];
		foreach (($by ?: []) as $r) {
			$k = sanitize_key((string) ($r['risk_level'] ?? ''));
			if ($k === '') {
				continue;
			}
			$by_risk[$k] = (int) ($r['c'] ?? 0);
		}

		// Спроба оцінити середню confidence із JSON explain.
		$rows = $this->db->get_results(
			$this->db->prepare(
				"SELECT explanation FROM {$this->table} WHERE created_at >= %s ORDER BY id DESC LIMIT 300",
				$since
			),
			ARRAY_A
		);
		$sum = 0.0;
		$n = 0;
		foreach (($rows ?: []) as $r) {
			$exp = isset($r['explanation']) && is_string($r['explanation']) ? json_decode((string) $r['explanation'], true) : null;
			if (!is_array($exp)) {
				continue;
			}
			$c = $this->extract_confidence($exp);
			if ($c > 0) {
				$sum += $c;
				$n++;
			}
		}
		$avg_conf = $n > 0 ? (string) number_format($sum / $n, 2) : '—';

		return [
			'total' => $total,
			'avg_confidence' => $avg_conf,
			'by_risk' => $by_risk,
		];
	}

	/**
	 * Нормалізація рядка БД у формат, який очікує адмін-центр.
	 *
	 * @param array<string,mixed> $row
	 * @return array<string,mixed>
	 */
	private function normalize_row(array $row): array {
		$row['id'] = (int) ($row['id'] ?? 0);
		$row['entity_id'] = (int) ($row['entity_id'] ?? 0);
		$row['tokens'] = (int) ($row['tokens'] ?? 0);
		$row['risk_level'] = sanitize_key((string) ($row['risk_level'] ?? 'low'));
		$row['entity_type'] = sanitize_key((string) ($row['entity_type'] ?? ''));

		$row['explanation'] = isset($row['explanation']) && is_string($row['explanation']) && $row['explanation'] !== ''
			? json_decode((string) $row['explanation'], true)
			: null;
		if (!is_array($row['explanation'])) {
			$row['explanation'] = null;
		}

		$row['confidence'] = 0.0;
		if (is_array($row['explanation'])) {
			$row['confidence'] = $this->extract_confidence($row['explanation']);
		}

		return $row;
	}

	/**
	 * @param array<string,mixed> $exp
	 */
	private function extract_confidence(array $exp): float {
		$c = 0.0;
		if (isset($exp['confidence'])) {
			$c = (float) $exp['confidence'];
		} elseif (isset($exp['meta']['confidence'])) {
			$c = (float) $exp['meta']['confidence'];
		} elseif (isset($exp['scores']['confidence'])) {
			$c = (float) $exp['scores']['confidence'];
		}
		if ($c < 0) {
			$c = 0;
		}
		if ($c > 1) {
			// Частина модулів може зберігати confidence у 0..100
			if ($c <= 100) {
				$c = $c / 100.0;
			} else {
				$c = 1.0;
			}
		}
		return (float) $c;
	}
}
