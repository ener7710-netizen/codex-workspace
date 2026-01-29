<?php
declare(strict_types=1);

namespace SEOJusAI\Repository;

defined('ABSPATH')||exit;

final class DecisionItemRepository {

    public static function add(string $decision_hash, int $post_id, string $taxonomy, array $prediction): void {
        global $wpdb;
        $table = $wpdb->prefix . 'seojusai_decision_items';

        $label = (string)($prediction['label'] ?? '');
        if ($label === '') return;

        $wpdb->insert(
            $table,
            [
                'decision_hash' => $decision_hash,
                'post_id' => $post_id,
                'taxonomy' => sanitize_key($taxonomy),
                'label' => sanitize_text_field($label),
                'confidence' => (float)($prediction['confidence'] ?? 0.0),
                'confidence_raw' => isset($prediction['confidence_raw']) ? (float)$prediction['confidence_raw'] : null,
                'rationale' => isset($prediction['rationale']) ? (string)$prediction['rationale'] : null,
                'evidence' => isset($prediction['evidence']) ? (string)$prediction['evidence'] : null,
                'created_at' => current_time('mysql', true),
            ],
            ['%s','%d','%s','%s','%f','%f','%s','%s','%s']
        );
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    public static function get_for_decision(string $decision_hash): array {
        global $wpdb;
        $table = $wpdb->prefix . 'seojusai_decision_items';
        return (array) $wpdb->get_results(
            $wpdb->prepare("SELECT * FROM $table WHERE decision_hash=%s ORDER BY id ASC", $decision_hash),
            ARRAY_A
        );
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    public static function get_approved_samples(string $taxonomy, int $limit = 500): array {
        global $wpdb;
        $table_items = $wpdb->prefix . 'seojusai_decision_items';
        $table_dec = $wpdb->prefix . 'seojusai_decisions';
        $limit = max(10, min(2000, $limit));

        $sql = "SELECT i.post_id, i.label, i.confidence
                FROM $table_items i
                JOIN $table_dec d ON d.decision_hash = i.decision_hash
                WHERE d.status = 'executed'
                  AND i.taxonomy = %s
                ORDER BY i.id DESC
                LIMIT $limit";

        return (array) $wpdb->get_results($wpdb->prepare($sql, sanitize_key($taxonomy)), ARRAY_A);
    }
}

    public static function get_latest_by_decision(string $decision_hash): array {
        global $wpdb;
        $table = $wpdb->prefix . 'seojusai_decision_items';
        return (array) $wpdb->get_results(
            $wpdb->prepare("SELECT * FROM $table WHERE decision_hash=%s ORDER BY id ASC", $decision_hash),
            ARRAY_A
        );
    }
