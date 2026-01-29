<?php
declare(strict_types=1);

namespace SEOJusAI\Features;

defined('ABSPATH') || exit;

/**
 * FeatureResolver
 * Читання/збереження прапорців + safe defaults.
 */
final class FeatureResolver {

    private const OPTION_KEY = 'seojusai_feature_flags';
    private const AUDIT_KEY  = 'seojusai_feature_flags_audit';

    /**
     * @return array<string,bool>
     */
    public static function get_all(): array {
        $stored = get_option(self::OPTION_KEY, []);
        if (!is_array($stored)) $stored = [];
        $defaults = FeatureRegistry::defaults();
        // Merge, stored wins but only for known flags
        $out = $defaults;
        foreach ($stored as $k => $v) {
            $k = (string)$k;
            if (!FeatureRegistry::exists($k)) continue;
            $out[$k] = (bool)$v;
        }
        return $out;
    }

    public static function enabled(string $key): bool {
        $all = self::get_all();
        return (bool)($all[$key] ?? false);
    }

    public static function set(string $key, bool $enabled, int $user_id = 0, string $note = ''): bool {
        if (!FeatureRegistry::exists($key)) {
            return false;
        }
        $all = self::get_all();
        $all[$key] = $enabled;
        $ok = update_option(self::OPTION_KEY, $all, false);
        if ($ok) {
            self::audit($key, $enabled, $user_id, $note);
        }
        return (bool)$ok;
    }

    public static function ensure_defaults(): void {
        $stored = get_option(self::OPTION_KEY, null);
        if ($stored === null) {
            update_option(self::OPTION_KEY, FeatureRegistry::defaults(), false);
        }
    }

    private static function audit(string $key, bool $enabled, int $user_id, string $note): void {
        $row = [
            'ts' => gmdate('c'),
            'key' => $key,
            'enabled' => $enabled,
            'user_id' => $user_id,
            'note' => $note ? sanitize_text_field($note) : '',
        ];
        $audit = get_option(self::AUDIT_KEY, []);
        if (!is_array($audit)) $audit = [];
        array_unshift($audit, $row);
        $audit = array_slice($audit, 0, 200);
        update_option(self::AUDIT_KEY, $audit, false);
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    public static function audit_log(): array {
        $audit = get_option(self::AUDIT_KEY, []);
        return is_array($audit) ? $audit : [];
    }
}
