<?php
declare(strict_types=1);

namespace SEOJusAI\Safety;

defined('ABSPATH') || exit;

/**
 * ApprovalService
 *
 * Зберігає короткоживучі "approval" для виконання ризикових дій.
 * Реалізація через transients (production-safe, без додаткових таблиць).
 */
final class ApprovalService {

    private const TTL_SECONDS = 172800; // 48h

    public static function approve(string $decision_id, int $user_id): void {
        if ($decision_id === '') {
            return;
        }
        set_transient(self::key($decision_id), [
            'approved_by' => $user_id,
            'approved_at' => time(),
        ], self::TTL_SECONDS);
    }

    public static function is_approved(string $decision_id): bool {
        if ($decision_id === '') {
            return false;
        }
        return (bool) get_transient(self::key($decision_id));
    }

    public static function revoke(string $decision_id): void {
        if ($decision_id === '') {
            return;
        }
        delete_transient(self::key($decision_id));
    }

    private static function key(string $decision_id): string {
        return 'seojusai_approval_' . md5($decision_id);
    }
}
