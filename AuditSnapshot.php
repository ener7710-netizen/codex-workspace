<?php
declare(strict_types=1);

namespace SEOJusAI\Analyze;

defined('ABSPATH') || exit;

final class AuditSnapshot {

    public const META_KEY = '_seojusai_audit_snapshot';

    /**
     * Ініціалізація snapshot (швидко, без AI)
     */
    public static function start(int $post_id, array $facts, array $violations): void {

        update_post_meta($post_id, self::META_KEY, [
            'status'       => 'pending', // pending | running | ready | error
            'facts'        => $facts,
            'violations'   => $violations,
            'ai'           => [],
            'started_at'   => time(),
            'finished_at'  => null,
        ]);
    }

    /**
     * Отримати snapshot
     */
    public static function get(int $post_id): array {
        $data = get_post_meta($post_id, self::META_KEY, true);
        return is_array($data) ? $data : [];
    }

    /**
     * Оновити snapshot (наприклад після AI)
     */
    public static function update(int $post_id, array $patch): void {
        $current = self::get($post_id);
        update_post_meta($post_id, self::META_KEY, array_merge($current, $patch));
    }

    /**
     * Помилка аналізу
     */
    public static function error(int $post_id, string $message): void {
        self::update($post_id, [
            'status' => 'error',
            'ai' => [
                'error' => $message,
            ],
            'finished_at' => time(),
        ]);
    }
}
