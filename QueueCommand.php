<?php
declare(strict_types=1);

namespace SEOJusAI\CLI;

use SEOJusAI\Tasks\TaskQueue;

defined('ABSPATH') || exit;

final class QueueCommand {

    public function register(): void {
        if (!class_exists('WP_CLI')) return;

        \WP_CLI::add_command('seojusai queue list', [$this, 'list']);
        \WP_CLI::add_command('seojusai queue retry', [$this, 'retry']);
    }

    public function list(array $args, array $assoc_args): void {
        $status = isset($assoc_args['status']) ? sanitize_key((string)$assoc_args['status']) : '';
        $limit  = isset($assoc_args['limit']) ? (int)$assoc_args['limit'] : 20;

        $q = new TaskQueue();
        $items = $q->list($limit, 0, $status);

        if (empty($items)) {
            \WP_CLI::log('Немає задач.');
            return;
        }

        $rows = [];
        foreach ($items as $it) {
            $rows[] = [
                'id' => (int)$it['id'],
                'action' => (string)$it['action'],
                'status' => (string)$it['status'],
                'attempts' => ((int)$it['attempts']) . '/' . ((int)$it['max_attempts']),
                'available_at' => (string)($it['available_at'] ?? ''),
            ];
        }

        \WP_CLI\Utils\format_items('table', $rows, ['id','action','status','attempts','available_at']);
    }

    public function retry(array $args, array $assoc_args): void {
        $id = isset($assoc_args['id']) ? (int)$assoc_args['id'] : 0;
        if ($id <= 0) {
            \WP_CLI::error('Вкажіть --id=');
            return;
        }
        $q = new TaskQueue();
        $ok = $q->retry_now($id);
        $ok ? \WP_CLI::success('Задачу поставлено в чергу повторно.') : \WP_CLI::error('Не вдалося оновити задачу.');
    }
}
