<?php
declare(strict_types=1);

namespace SEOJusAI\Learning;

use wpdb;

defined('ABSPATH') || exit;

final class LearningEventRepository {

    private wpdb $db;
    private string $table;

    public function __construct(?wpdb $db=null) {
        global $wpdb;
        $this->db = $db instanceof wpdb ? $db : $wpdb;
        $this->table = $this->db->prefix . 'seojusai_learning_events';
    }

    public function create(array $row): int {
        $defaults = [
            'decision_hash' => '',
            'module_slug' => '',
            'action_slug' => '',
            'entity_type' => 'post',
            'entity_id' => 0,
            'predicted_roi' => 0.0,
            'predicted_impact' => 0.0,
            'predicted_risk' => 'low',
            'confidence' => 0.0,
            'before_metrics' => null,
            'after_metrics' => null,
            'outcome' => null,
            'applied_at' => current_time('mysql', true),
            'observe_after' => gmdate('Y-m-d H:i:s', time() + 7 * DAY_IN_SECONDS),
            'observed_at' => null,
            'status' => 'scheduled',
            'created_at' => current_time('mysql', true),
            'updated_at' => current_time('mysql', true),
        ];
        $row = array_merge($defaults, $row);

        $row['decision_hash'] = sanitize_text_field((string)$row['decision_hash']);
        if ($row['decision_hash'] === '') return 0;

        $row['module_slug'] = sanitize_key((string)$row['module_slug']);
        $row['action_slug'] = sanitize_key((string)$row['action_slug']);
        $row['entity_type'] = sanitize_key((string)$row['entity_type']);
        $row['entity_id'] = max(0, (int)$row['entity_id']);
        $row['predicted_roi'] = (float)$row['predicted_roi'];
        $row['predicted_impact'] = (float)$row['predicted_impact'];
        $row['predicted_risk'] = in_array((string)$row['predicted_risk'], ['low','medium','high'], true) ? (string)$row['predicted_risk'] : 'low';
        $row['confidence'] = max(0.0, min(1.0, (float)$row['confidence']));

        $formats = ['%s','%s','%s','%s','%d','%f','%f','%s','%f','%s','%s','%s','%s','%s','%s','%s','%s'];
        $ok = $this->db->insert($this->table, $row, $formats);

        if ($ok === false) return 0;
        return (int)$this->db->insert_id;
    }

    public function get_by_hash(string $decision_hash): ?array {
        $decision_hash = sanitize_text_field($decision_hash);
        if ($decision_hash === '') return null;

        $row = $this->db->get_row(
            $this->db->prepare("SELECT * FROM {$this->table} WHERE decision_hash=%s LIMIT 1", $decision_hash),
            ARRAY_A
        );
        if (!$row) return null;

        foreach (['before_metrics','after_metrics','outcome'] as $k) {
            $row[$k] = isset($row[$k]) && is_string($row[$k]) && $row[$k] !== '' ? json_decode((string)$row[$k], true) : null;
            if (!is_array($row[$k])) $row[$k] = null;
        }
        $row['confidence'] = isset($row['confidence']) ? (float)$row['confidence'] : 0.0;
        return $row;
    }

    /** @return array<int,array<string,mixed>> */
    public function list_recent(int $limit=50, string $status=''): array {
        $limit = max(1, min(200, $limit));
        $status = $status ? sanitize_key($status) : '';
        if ($status) {
            $rows = $this->db->get_results(
                $this->db->prepare("SELECT * FROM {$this->table} WHERE status=%s ORDER BY id DESC LIMIT %d", $status, $limit),
                ARRAY_A
            );
        } else {
            $rows = $this->db->get_results(
                $this->db->prepare("SELECT * FROM {$this->table} ORDER BY id DESC LIMIT %d", $limit),
                ARRAY_A
            );
        }
        return $rows ?: [];
    }

    /** @return array<int,array<string,mixed>> */
    public function due(int $limit=50): array {
        $limit = max(1, min(200, $limit));
        $now = current_time('mysql', true);
        $rows = $this->db->get_results(
            $this->db->prepare(
                "SELECT * FROM {$this->table} WHERE status IN ('scheduled','retry') AND observe_after <= %s ORDER BY observe_after ASC LIMIT %d",
                $now, $limit
            ),
            ARRAY_A
        );
        return $rows ?: [];
    }

    public function mark_observed(int $id, array $after_metrics, array $outcome, string $status='observed'): bool {
        $id = max(0, (int)$id);
        if ($id <= 0) return false;

        $data = [
            'after_metrics' => wp_json_encode($after_metrics, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),
            'outcome' => wp_json_encode($outcome, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),
            'observed_at' => current_time('mysql', true),
            'status' => sanitize_key($status),
            'updated_at' => current_time('mysql', true),
        ];
        return $this->db->update($this->table, $data, ['id'=>$id]) !== false;
    }

    public function set_before_metrics(int $id, array $before_metrics): bool {
        $id = max(0, (int)$id);
        if ($id <= 0) return false;

        $data = [
            'before_metrics' => wp_json_encode($before_metrics, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),
            'updated_at' => current_time('mysql', true),
        ];
        return $this->db->update($this->table, $data, ['id'=>$id]) !== false;
    }

    public function reschedule(int $id, int $days): bool {
        $id = max(0, (int)$id);
        $days = max(1, min(60, $days));
        $data = [
            'observe_after' => gmdate('Y-m-d H:i:s', time() + $days * DAY_IN_SECONDS),
            'status' => 'retry',
            'updated_at' => current_time('mysql', true),
        ];
        return $this->db->update($this->table, $data, ['id'=>$id]) !== false;
    }
}
