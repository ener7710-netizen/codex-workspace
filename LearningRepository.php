<?php
declare(strict_types=1);

namespace SEOJusAI\Learning;

use wpdb;

defined('ABSPATH') || exit;

final class LearningRepository {

    private string $table;

    public function __construct(?wpdb $db = null) {
        global $wpdb;
        $db = $db instanceof wpdb ? $db : $wpdb;
        $this->table = $db->prefix . 'seojusai_learning';
    }

    public function insert(array $row): bool {
        global $wpdb;
        $row['created_at'] = current_time('mysql', true);
        return $wpdb->insert($this->table, $row) !== false;
    }

    /** @return array<int,array<string,mixed>> */
    public function recent(int $days = 90, int $limit = 200): array {
        global $wpdb;
        $days = max(7, min(365, $days));
        $limit = max(10, min(1000, $limit));
        $since = gmdate('Y-m-d H:i:s', time() - $days * 86400);

        return $wpdb->get_results(
            $wpdb->prepare("SELECT * FROM {$this->table} WHERE created_at >= %s ORDER BY id DESC LIMIT %d", $since, $limit),
            ARRAY_A
        ) ?: [];
    }
}
