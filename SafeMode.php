<?php
declare(strict_types=1);

namespace SEOJusAI\Safety;

defined('ABSPATH') || exit;

/**
 * SafeMode
 *
 * Read-only / hardening режим: блокує застосування змін (apply/redirects/schema writes).
 *
 * NOTE:
 * - Older code used is_enabled()/enable()/disable().
 * - Newer UI/pages expect is_active() + ability to store a reason.
 * We keep full backward compatibility.
 */
final class SafeMode {

    private const OPTION_KEY = 'seojusai_safe_mode';

    private static function read_option(): array {
        $raw = get_option(self::OPTION_KEY, false);

        // Legacy boolean option.
        if (is_bool($raw) || $raw === 0 || $raw === 1 || $raw === '0' || $raw === '1') {
            $enabled = (bool) $raw;
            return [
                'enabled' => $enabled,
                'reason'  => $enabled ? 'manual' : '',
                'ts'      => 0,
            ];
        }

        if (is_array($raw)) {
            return [
                'enabled' => (bool)($raw['enabled'] ?? false),
                'reason'  => (string)($raw['reason'] ?? ''),
                'ts'      => (int)($raw['ts'] ?? 0),
            ];
        }

        return ['enabled' => false, 'reason' => '', 'ts' => 0];
    }

    private static function write_option(bool $enabled, string $reason = ''): void {
        update_option(self::OPTION_KEY, [
            'enabled' => $enabled,
            'reason'  => $reason,
            'ts'      => time(),
        ], false);
    }

    /** Backward compatible */
    public static function is_enabled(): bool {
        return self::is_active();
    }

    /** Preferred */
    public static function is_active(): bool {
        $opt = self::read_option();
        return (bool) $opt['enabled'];
    }

    public static function reason(): string {
        $opt = self::read_option();
        return (string) $opt['reason'];
    }

    /** Backward compatible */
    public static function enable(): void {
        self::activate('manual');
    }

    /** Backward compatible */
    public static function disable(): void {
        self::deactivate();
    }

    public static function activate(string $reason = 'manual'): void {
        self::write_option(true, $reason);
    }

    public static function deactivate(): void {
        self::write_option(false, '');
    }
}
