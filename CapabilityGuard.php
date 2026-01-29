<?php
declare(strict_types=1);

namespace SEOJusAI\Capabilities;

defined('ABSPATH') || exit;

/**
 * CapabilityGuard
 *
 * Централізована перевірка прав. Якщо capability відсутня в системі,
 * fallback на manage_options для backward-compat.
 */
final class CapabilityGuard {

    /**
     * @param string $cap
     * @param array<string,mixed> $context
     */
    public static function can(string $cap, array $context = []): bool {
        if (!function_exists('current_user_can')) {
            return false;
        }

        // Якщо cap зареєстрована — використовуємо її
        if (self::cap_exists($cap)) {
            return current_user_can($cap);
        }

        // Backward compat
        return current_user_can('manage_options');
    }

    /**
     * @param string[] $caps
     * @param array<string,mixed> $context
     */
    public static function any(array $caps, array $context = []): bool {
        foreach ($caps as $cap) {
            if (self::can((string)$cap, $context)) {
                return true;
            }
        }
        return false;
    }

    private static function cap_exists(string $cap): bool {
        // Role objects know capabilities only after added; treat our map as authoritative.
        return in_array($cap, CapabilityMap::all(), true);
    }
}
