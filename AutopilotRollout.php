<?php
declare(strict_types=1);

namespace SEOJusAI\Autopilot;

defined('ABSPATH') || exit;

/**
 * AutopilotRollout
 *
 * Поетапний rollout FULL SAFE автопілота.
 *
 * ✅ Deterministic sampling: однакові URL стабільно входять/виходять зі scope.
 * ✅ Auto mode: збільшує coverage тільки при здорових метриках.
 * ✅ Hardening: при PAUSE/інциденті coverage скидається до 1%.
 */
final class AutopilotRollout {

    private const OPTION_KEY = 'seojusai_autopilot';

    /** @var int[] */
    private const STEPS = [1, 5, 25, 100];

    public static function mode(): string {
        $opt = get_option(self::OPTION_KEY, []);
        if (!is_array($opt)) $opt = [];
        $mode = sanitize_key((string)($opt['rollout_mode'] ?? 'auto'));
        return in_array($mode, ['auto','manual'], true) ? $mode : 'auto';
    }

    public static function percent(): int {
        $opt = get_option(self::OPTION_KEY, []);
        if (!is_array($opt)) $opt = [];
        $p = (int)($opt['rollout_percent'] ?? 1);
        return self::normalize_percent($p);
    }

    public static function set(string $mode, int $percent): void {
        $mode = sanitize_key($mode);
        if (!in_array($mode, ['auto','manual'], true)) $mode = 'auto';
        $percent = self::normalize_percent($percent);

        $opt = get_option(self::OPTION_KEY, []);
        if (!is_array($opt)) $opt = [];
        $opt['rollout_mode'] = $mode;
        $opt['rollout_percent'] = $percent;
        update_option(self::OPTION_KEY, $opt, false);
    }

    /**
     * Deterministic check: чи потрапляє post_id у rollout-coverage.
     */
    public static function allows_post(int $post_id, ?int $percent = null): bool {
        $post_id = (int)$post_id;
        if ($post_id <= 0) return false;
        $p = $percent === null ? self::percent() : self::normalize_percent((int)$percent);
        if ($p <= 0) return false;
        if ($p >= 100) return true;

        $salt = (string) (defined('AUTH_SALT') ? AUTH_SALT : (string) get_site_url());
        $key = $salt . '|' . (string) get_current_blog_id() . '|' . (string) $post_id;
        $h = crc32($key);
        $bucket = (int) ($h % 100);
        return $bucket < $p;
    }

    /**
     * Повертає наступний rollout step.
     */
    public static function next_step(int $current): int {
        $current = self::normalize_percent($current);
        foreach (self::STEPS as $step) {
            if ($step > $current) return $step;
        }
        return 100;
    }

    public static function normalize_percent(int $p): int {
        $p = (int)$p;
        if ($p < 0) $p = 0;
        if ($p > 100) $p = 100;
        // align to known steps if close; otherwise keep exact for manual fine-tune
        return $p;
    }
}
