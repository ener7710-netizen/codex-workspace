<?php
declare(strict_types=1);

namespace SEOJusAI\Bulk;

use SEOJusAI\Tasks\TaskQueue;

defined('ABSPATH') || exit;

final class BulkPlanner {

    private TaskQueue $queue;

    public function __construct(?TaskQueue $queue=null) {
        $this->queue = $queue ?? new TaskQueue();
    }

    public function plan_audit(int $job_id, array $filters): void {
        $ids = $this->select_posts($filters);
        (new BulkJobRepository())->set_total($job_id, count($ids));

        foreach ($ids as $post_id) {
            $payload = [
                'post_id' => (int)$post_id,
                'bulk_job_id' => $job_id,
                'priority' => 'low',
            ];
            $key = 'bulk-audit-'.$job_id.'-'.$post_id;
            $this->queue->enqueue('page_audit', $payload, $key);
        }
    }

    public function plan_apply(int $job_id, array $filters): void {
        $ids = $this->select_posts($filters);
        (new BulkJobRepository())->set_total($job_id, count($ids));

        foreach ($ids as $post_id) {
            $payload = [
                'post_id' => (int)$post_id,
                'bulk_job_id' => $job_id,
                'priority' => 'medium',
            ];
            $key = '// disabled: \1
            $this->queue->enqueue('apply_recommendations', $payload, $key);
        }
    }

    public function plan_rollback(int $job_id, array $filters): void {
        $ids = $this->select_posts($filters);
        (new BulkJobRepository())->set_total($job_id, count($ids));

        foreach ($ids as $post_id) {
            $payload = [
                'post_id' => (int)$post_id,
                'bulk_job_id' => $job_id,
                'priority' => 'high',
            ];
            $key = 'bulk-rollback-'.$job_id.'-'.$post_id;
            $this->queue->enqueue('rollback_last', $payload, $key);
        }
    }

    private function select_posts(array $filters): array {
        $post_types = (array)($filters['post_types'] ?? ['post','page']);
        $statuses   = (array)($filters['statuses'] ?? ['publish']);
        $limit      = (int)($filters['limit'] ?? 200);

        $args = [
            'post_type' => $post_types,
            'post_status' => $statuses,
            'fields' => 'ids',
            'posts_per_page' => $limit,
            'orderby' => 'ID',
            'order' => 'DESC',
        ];

        $q = new \WP_Query($args);
        return array_map('intval', (array)$q->posts);
    }
}
