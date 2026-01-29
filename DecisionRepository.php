<?php
declare(strict_types=1);

namespace SEOJusAI\Decisions;

defined('ABSPATH') || exit;

final class DecisionRepository {

    public static function getLatestByPost(int $postId, int $limit = 5): array {
        global $wpdb;
        $table = $wpdb->prefix.'seojusai_decisions';
        return $wpdb->get_results($wpdb->prepare("SELECT * FROM $table WHERE post_id=%d ORDER BY id DESC LIMIT %d", $postId, $limit));
    }


    private function table(): string {
        global $wpdb;
        return $wpdb->prefix . 'seojusai_decisions';
    }

    /**
     * @param array<string,mixed> $decision
     * @param array<string,mixed> $ctx
     * @return int decision_id
     */
    public function create(array $decision, array $ctx): int {
        global $wpdb;

        $post_id = (int) ($ctx['post_id'] ?? 0);
        $source = sanitize_key((string)($ctx['source'] ?? 'unknown'));
        $context_type = sanitize_key((string)($ctx['context_type'] ?? 'page'));
        if ($context_type === '') $context_type = 'page';

        $risk = sanitize_key((string)($decision['risk_level'] ?? ($decision['risk'] ?? 'unknown')));
        $confidence = (float)($decision['confidence'] ?? 0.0);
        if ($confidence < 0) $confidence = 0;
        if ($confidence > 1) $confidence = 1;

        $explain_id = sanitize_text_field((string)($decision['explain_id'] ?? ($decision['explainId'] ?? '')));
        $actions = $decision['actions'] ?? null;

        $hash = (string)($decision['decision_hash'] ?? ($decision['hash'] ?? ''));
        if ($hash === '') {
            $hash = hash('sha256', wp_json_encode($decision));
        }
        $hash = substr(preg_replace('/[^a-f0-9]/', '', strtolower($hash)) ?: '', 0, 64);

        $data = [
            'created_at' => time(),
            'source' => substr($source, 0, 32),
            'context_type' => substr($context_type, 0, 16),
            'post_id' => $post_id,
            'explain_id' => substr($explain_id, 0, 64),
            'risk_level' => substr($risk, 0, 16),
            'confidence' => round($confidence, 2),
            'decision_hash' => $hash,
            'actions_json' => is_array($actions) ? wp_json_encode($actions, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null,
            'meta' => isset($ctx['meta']) ? wp_json_encode($ctx['meta'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null,
        ];

        $wpdb->insert($this->table(), $data);
        return (int)$wpdb->insert_id;
    }

    /** @return array<int,array<string,mixed>> */
    public function list_recent(int $days = 30, int $limit = 50): array {
        global $wpdb;
        $days = max(1, min(365, $days));
        $limit = max(1, min(200, $limit));
        $since = time() - ($days * DAY_IN_SECONDS);

        $sql = $wpdb->prepare(
            "SELECT * FROM {$this->table()} WHERE created_at >= %d ORDER BY created_at DESC LIMIT %d",
            $since, $limit
        );
        $rows = $wpdb->get_results($sql, ARRAY_A);
        return is_array($rows) ? $rows : [];
    }
}
