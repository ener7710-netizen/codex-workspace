<?php
declare(strict_types=1);

namespace SEOJusAI\Bulk;

defined('ABSPATH') || exit;

final class BulkJobRepository {

    private string $table;

    public function __construct() {
        global $wpdb;
        $this->table = $wpdb->prefix . 'seojusai_bulk_jobs';
    }

    public function create(string $type, array $filters, int $user_id): int {
        global $wpdb;
        $now = current_time('mysql');
        $wpdb->insert($this->table, [
            'user_id' => $user_id,
            'job_type' => sanitize_key($type),
            'filters_json' => (string) wp_json_encode($filters),
            'status' => 'pending',
            'total_items' => 0,
            'processed_items' => 0,
            'success_items' => 0,
            'failed_items' => 0,
            'created_at' => $now,
            'updated_at' => $now,
        ], ['%d','%s','%s','%s','%d','%d','%d','%d','%s','%s']);

        return (int) $wpdb->insert_id;
    }

    public function set_total(int $id, int $total): void {
        global $wpdb;
        $wpdb->update($this->table, [
            'total_items' => $total,
            'updated_at' => current_time('mysql'),
        ], ['id'=>$id], ['%d','%s'], ['%d']);
    }

    public function bump(int $id, bool $ok, ?string $error=null): void {
        global $wpdb;
        $row = $wpdb->get_row($wpdb->prepare("SELECT processed_items, success_items, failed_items, total_items, status FROM {$this->table} WHERE id=%d", $id), ARRAY_A);
        if (!$row) return;
        $processed = (int)$row['processed_items'] + 1;
        $success = (int)$row['success_items'] + ($ok ? 1 : 0);
        $failed = (int)$row['failed_items'] + ($ok ? 0 : 1);
        $status = (string)$row['status'];
        $total = (int)$row['total_items'];

        $update = [
            'processed_items' => $processed,
            'success_items' => $success,
            'failed_items' => $failed,
            'updated_at' => current_time('mysql'),
        ];
        if (!$ok && $error) $update['last_error'] = wp_strip_all_tags($error);

        if ($total > 0 && $processed >= $total && $status !== 'cancelled') {
            $update['status'] = $failed > 0 ? 'completed' : 'completed';
        }

        $wpdb->update($this->table, $update, ['id'=>$id]);
    }

    public function set_status(int $id, string $status): void {
        global $wpdb;
        $wpdb->update($this->table, [
            'status' => sanitize_key($status),
            'updated_at' => current_time('mysql'),
        ], ['id'=>$id]);
    }



public function approve(int $id, int $user_id, int $ttl_seconds = 172800, ?string $note=null): bool {
    global $wpdb;
    $now = current_time('mysql');
    $until = gmdate('Y-m-d H:i:s', time() + max(300, $ttl_seconds));
    return $wpdb->update($this->table, [
        'approved_by' => (int)$user_id,
        'approved_at' => $now,
        'approved_until' => $until,
        'approval_note' => $note ? sanitize_text_field($note) : null,
        'status' => 'running',
        'updated_at' => $now,
    ], ['id'=>$id], ['%d','%s','%s','%s','%s','%s'], ['%d']) !== false;
}

public function revoke_approval(int $id): bool {
    global $wpdb;
    $now = current_time('mysql');
    return $wpdb->update($this->table, [
        'approved_by' => 0,
        'approved_at' => null,
        'approved_until' => null,
        'approval_note' => null,
        'status' => 'awaiting_approval',
        'updated_at' => $now,
    ], ['id'=>$id]) !== false;
}

public function is_approved(int $id): bool {
    $job = $this->get($id);
    if (!$job) return false;
    $until = (string)($job['approved_until'] ?? '');
    if (!$until) return false;
    return strtotime($until) > time();
}

public function get(int $id): ?array {
        global $wpdb;
        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$this->table} WHERE id=%d", $id), ARRAY_A);
        if (!$row) return null;
        $row['filters'] = json_decode((string)$row['filters_json'], true) ?: [];
        return $row;
    }

    public function list(int $limit=20): array {
        global $wpdb;
        $rows = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$this->table} ORDER BY id DESC LIMIT %d", $limit), ARRAY_A);
        foreach ($rows as &$r) {
            $r['filters'] = json_decode((string)$r['filters_json'], true) ?: [];
        }
        return $rows ?: [];
    }
}
