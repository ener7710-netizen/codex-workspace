<?php
declare(strict_types=1);

namespace SEOJusAI\Execution;

defined('ABSPATH') || exit;

use SEOJusAI\Execution\DTO\ExecutionIntentDTO;

/**
 * AnalysisResultRepository
 *
 * Storage-only output for analysis execution results.
 * @boundary Read-only intelligence gathering; no site mutations.
 */
final class AnalysisResultRepository
{
    private string $table;

    public function __construct()
    {
        global $wpdb;
        $this->table = $wpdb->prefix . 'seojusai_analysis_results';
    }

    private function tableExists(): bool
    {
        global $wpdb;
        $like = $this->table;
        $found = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $like));
        return is_string($found) && $found === $like;
    }

    /**
     * Store a result for intent once (no overwrite).
     *
     * @param array<string,mixed> $result
     */
    public function storeOnce(int $intentId, int $postId, array $result): bool
    {
        if (!$this->tableExists()) {
            return false;
        }

        global $wpdb;

        // One result per intent.
        $existing = $wpdb->get_var(
            $wpdb->prepare("SELECT id FROM {$this->table} WHERE intent_id = %d LIMIT 1", (int)$intentId)
        );
        if ($existing) {
            return true;
        }

        $json = wp_json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (!is_string($json)) {
            $json = '{}';
        }

        $now = current_time('mysql', 1);

        $inserted = $wpdb->insert(
            $this->table,
            [
                'intent_id'    => (int) $intentId,
                'post_id'      => (int) $postId,
                'result_json'  => $json,
                'created_at'   => $now,
            ],
            ['%d','%d','%s','%s']
        );

        return $inserted === 1;
    }

    /** @return array<string,mixed>|null */
    public function getByIntentId(int $intentId): ?array
    {
        if (!$this->tableExists()) {
            return null;
        }

        global $wpdb;

        $row = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$this->table} WHERE intent_id = %d LIMIT 1", (int)$intentId),
            ARRAY_A
        );
        if (!is_array($row) || !isset($row['result_json'])) {
            return null;
        }

        $decoded = json_decode((string)$row['result_json'], true);
        return is_array($decoded) ? $decoded : null;
    }
}
