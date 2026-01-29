<?php
declare(strict_types=1);

namespace SEOJusAI\Decisions;

defined('ABSPATH') || exit;

final class OutcomeRepository {

    private function table(): string {
        global $wpdb;
        return $wpdb->prefix . 'seojusai_outcomes';
    }

    /**
     * @param array<string,mixed> $row
     */
    public function upsert(int $decision_id, array $row): void {
        global $wpdb;
        if ($decision_id <= 0) return;

        $data = [
            'decision_id' => $decision_id,
            'created_at' => isset($row['created_at']) ? (int)$row['created_at'] : time(),
            'status' => substr(sanitize_key((string)($row['status'] ?? 'unknown')), 0, 16),
            'snapshot_id' => isset($row['snapshot_id']) ? (int)$row['snapshot_id'] : 0,
            'lead_delta' => isset($row['lead_delta']) ? (int)$row['lead_delta'] : 0,
            'conversion_delta' => isset($row['conversion_delta']) ? (int)$row['conversion_delta'] : 0,
            'trust_delta' => isset($row['trust_delta']) ? (int)$row['trust_delta'] : 0,
            'meta' => isset($row['meta']) ? wp_json_encode($row['meta'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null,
        ];

        $existing = (int)$wpdb->get_var($wpdb->prepare("SELECT id FROM {$this->table()} WHERE decision_id = %d", $decision_id));
        if ($existing > 0) {
            $wpdb->update($this->table(), $data, ['decision_id' => $decision_id]);
        } else {
            $wpdb->insert($this->table(), $data);
        }
    }
}
