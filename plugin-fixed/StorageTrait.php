<?php
declare(strict_types=1);

namespace SEOJusAI\AI\Engine;

defined('ABSPATH') || exit;

trait StorageTrait {

    private static function store_analysis_data(int $post_id, array $data): void {
        update_post_meta($post_id, '_seojusai_analysis_data', $data);

        if (class_exists('\SEOJusAI\Core\SnapshotManager')) {
            try {
                \SEOJusAI\Core\SnapshotManager::capture_page_snapshot($post_id);
            } catch (\Throwable $e) {
            }
        }
    }

    public static function get_last_analysis(int $post_id): array {
        if ($post_id <= 0) {
            return self::empty_result('Некоректний post_id');
        }

        $data = get_post_meta($post_id, '_seojusai_analysis_data', true);

        if (!is_array($data) || empty($data)) {
            return self::empty_result('Аудит ще не проводився.');
        }

        return [
            'ok'        => true,
            'post_id'   => $post_id,
            'score'     => (int) ($data['score'] ?? 0),
            'analysis'  => (array) ($data['analysis'] ?? []),
            'tasks'     => (array) ($data['tasks'] ?? []),
            'facts'     => (array) ($data['facts'] ?? []),
            'updated'   => (string) ($data['updated_at'] ?? ''),
            'mode'      => (string) ($data['mode'] ?? 'no_ai'),
        ];
    }

    private static function empty_result(string $message): array {
        return [
            'ok'      => false,
            'score'   => 0,
            'analysis'=> [],
            'tasks'   => [],
            'facts'   => [],
            'message' => $message,
        ];
    }
}
