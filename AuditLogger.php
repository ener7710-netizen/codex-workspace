<?php
declare(strict_types=1);

namespace SEOJusAI\Audit;

defined('ABSPATH')||exit;

final class AuditLogger {

    public static function log(
        string $decision_hash,
        string $entity_type,
        int $entity_id,
        string $event,
        string $message,
        array $context = []
    ): void {
        global $wpdb;
        $table = $wpdb->prefix . 'seojusai_audit';
        $wpdb->insert(
            $table,
            [
                'decision_hash' => $decision_hash,
                'entity_type' => $entity_type,
                'entity_id' => $entity_id,
                'event' => $event,
                'message' => $message,
                'context' => wp_json_encode($context),
                'created_at' => current_time('mysql', true),
            ],
            ['%s','%s','%d','%s','%s','%s','%s']
        );
    }
}