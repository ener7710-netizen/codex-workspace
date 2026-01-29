<?php
declare(strict_types=1);

namespace SEOJusAI\AIMonitoring\Conversion;

defined('ABSPATH') || exit;

final class ConversionRepository {

    private function table(): string {
        global $wpdb;
        return $wpdb->prefix . 'seojusai_conversions';
    }

    /** @param array<string,mixed> $row */
    public function insert(array $row): void {
        global $wpdb;

        $data = [
            'ts' => isset($row['ts']) ? (int)$row['ts'] : time(),
            'post_id' => isset($row['post_id']) ? (int)$row['post_id'] : 0,
            'source' => isset($row['source']) ? sanitize_key((string)$row['source']) : 'unknown',
            'event_type' => isset($row['event_type']) ? sanitize_key((string)$row['event_type']) : 'unknown',
            'session_id' => isset($row['session_id']) ? sanitize_text_field((string)$row['session_id']) : '',
            'value_int' => isset($row['value_int']) ? (int)$row['value_int'] : 0,
            'meta' => isset($row['meta']) ? wp_json_encode($row['meta'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null,
        ];

        // clamp
        $data['source'] = substr($data['source'], 0, 32);
        $data['event_type'] = substr($data['event_type'], 0, 32);
        $data['session_id'] = substr($data['session_id'], 0, 64);

        $wpdb->insert($this->table(), $data);
    }

    /** @return array<int,array<string,mixed>> */
    public function stats_by_source(int $days = 30, string $event_type_filter = ''): array {
        global $wpdb;
        $days = max(1, min(365, $days));
        $since = time() - ($days * DAY_IN_SECONDS);

        $event_type_filter = $event_type_filter !== '' ? sanitize_key($event_type_filter) : '';

        $sql = $wpdb->prepare(
            "SELECT source, event_type, COUNT(*) as cnt
             FROM {$this->table()}
             WHERE ts >= %d
             AND (%s = '' OR event_type = %s)
             GROUP BY source, event_type
             ORDER BY cnt DESC",
            $since,
            $event_type_filter,
            $event_type_filter
        );

        $rows = $wpdb->get_results($sql, ARRAY_A);
        return is_array($rows) ? $rows : [];
    }

    /** @return array<int,array<string,mixed>> */
    public function top_posts(int $days = 30, int $limit = 20, string $source = '', string $event_type_filter = ''): array {
        global $wpdb;
        $days = max(1, min(365, $days));
        $limit = max(1, min(200, $limit));
        $since = time() - ($days * DAY_IN_SECONDS);

        $source = $source !== '' ? sanitize_key($source) : '';
        $event_type_filter = $event_type_filter !== '' ? sanitize_key($event_type_filter) : '';

        if ($source !== '') {
            $sql = $wpdb->prepare(
                "SELECT post_id, COUNT(*) as cnt
                 FROM {$this->table()}
                 WHERE ts >= %d AND source = %s AND post_id > 0
                 AND (%s = '' OR event_type = %s)
                 GROUP BY post_id
                 ORDER BY cnt DESC
                 LIMIT %d",
                $since, $source, $event_type_filter, $event_type_filter, $limit
            );
        } else {
            $sql = $wpdb->prepare(
                "SELECT post_id, COUNT(*) as cnt
                 FROM {$this->table()}
                 WHERE ts >= %d AND post_id > 0
                 AND (%s = '' OR event_type = %s)
                 GROUP BY post_id
                 ORDER BY cnt DESC
                 LIMIT %d",
                $since, $event_type_filter, $event_type_filter, $limit
            );
        }

        $rows = $wpdb->get_results($sql, ARRAY_A);
        return is_array($rows) ? $rows : [];
    }


    public function count_total(int $days = 30, string $source = '', string $event_type_filter = ''): int {
        global $wpdb;
        $days = max(1, min(365, $days));
        $since = time() - ($days * DAY_IN_SECONDS);

        $source = $source !== '' ? sanitize_key($source) : '';
        $event_type_filter = $event_type_filter !== '' ? sanitize_key($event_type_filter) : '';

        if ($source !== '') {
            $sql = $wpdb->prepare(
                "SELECT COUNT(*) FROM {$this->table()} WHERE ts >= %d AND source = %s AND (%s = '' OR event_type = %s)",
                $since, $source, $event_type_filter, $event_type_filter
            );
        } else {
            $sql = $wpdb->prepare(
                "SELECT COUNT(*) FROM {$this->table()} WHERE ts >= %d AND (%s = '' OR event_type = %s)",
                $since, $event_type_filter, $event_type_filter
            );
        }
        $v = $wpdb->get_var($sql);
        return (int)$v;
    }

}
