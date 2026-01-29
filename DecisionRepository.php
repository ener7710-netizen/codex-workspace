<?php
declare(strict_types=1);

namespace SEOJusAI\Repository;

use SEOJusAI\Domain\DecisionRecord;

defined('ABSPATH')||exit;

final class DecisionRepository {

    public static function save(DecisionRecord $d): void {
        global $wpdb;
        $table=$wpdb->prefix.'seojusai_decisions';
        $wpdb->replace($table,[
            'decision_hash'=>$d->decisionHash,
            'post_id'=>$d->postId,
            'score'=>$d->score,
            'summary'=>$d->summary,
            'status'=>$d->status,
            'created_at'=>current_time('mysql',true),
        ],['%s','%d','%f','%s','%s','%s']);
    }

    public static function get_by_post(int $post_id): array {
        global $wpdb;
        $table=$wpdb->prefix.'seojusai_decisions';
        return $wpdb->get_results($wpdb->prepare("SELECT * FROM $table WHERE post_id=%d ORDER BY id DESC",$post_id));
    }

    public static function get(string $decision_hash): ?object {
        global $wpdb;
        $table=$wpdb->prefix.'seojusai_decisions';
        return $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE decision_hash=%s",$decision_hash));
    }

public static function mark_executed(string $decision_hash): void {
    global $wpdb;
    $table = $wpdb->prefix . 'seojusai_decisions';
    $wpdb->update(
        $table,
        ['status' => 'executed'],
        ['decision_hash' => $decision_hash],
        ['%s'],
        ['%s']
    );
}

public static function mark_cancelled(string $decision_hash, string $reason = ''): void {
    global $wpdb;
    $table = $wpdb->prefix . 'seojusai_decisions';
    $wpdb->update(
        $table,
        ['status' => 'cancelled', 'summary' => $reason ?: 'Cancelled'],
        ['decision_hash' => $decision_hash],
        ['%s','%s'],
        ['%s']
    );
}

    /**
     * Filter decisions by optional post ID, status and confidence threshold.
     *
     * @param array<string,mixed> $args {
     *     Optional. Array of query arguments.
     *
     *     @type int    $post_id    Optional post ID to filter by.
     *     @type string $status     Optional status (planned/approved/rejected/cancelled/executed).
     *     @type string $confidence Optional minimum confidence value (0..1) as string/float.
     * }
     *
     * @return array<int,object> List of decision records.
     */
    public static function filter(array $args): array {
        global $wpdb;
        $table = $wpdb->prefix . 'seojusai_decisions';
        $where  = [];
        $params = [];

        // Filter by post ID if provided
        if (!empty($args['post_id'])) {
            $where[] = 'post_id = %d';
            $params[] = (int) $args['post_id'];
        }

        // Filter by status if provided
        if (!empty($args['status'])) {
            $where[] = 'status = %s';
            $params[] = (string) $args['status'];
        }

        // Filter by minimum confidence threshold if provided and numeric
        if ($args['confidence'] !== '' && is_numeric($args['confidence'])) {
            $where[] = 'confidence >= %f';
            $params[] = (float) $args['confidence'];
        }

        $sql = "SELECT * FROM {$table}";
        if ($where) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }
        $sql .= ' ORDER BY id DESC LIMIT 100';

        // Prepare query with parameters if any
        $prepared = $params ? $wpdb->prepare($sql, ...$params) : $sql;
        $rows = $wpdb->get_results($prepared);
        return is_array($rows) ? $rows : [];
    }
}
