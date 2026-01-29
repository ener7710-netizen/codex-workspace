<?php
declare(strict_types=1);

namespace SEOJusAI\Vectors;

defined('ABSPATH') || exit;

final class VectorVersion {

    public static function option_key(string $namespace): string {
        $namespace = sanitize_key($namespace ?: 'default');
        return 'seojusai_vectors_version_' . $namespace;
    }

    public static function current(string $namespace='default'): int {
        $v = (int) get_option(self::option_key($namespace), 1);
        return max(1, $v);
    }

    public static function bump(string $namespace='default'): int {
        $v = self::current($namespace) + 1;
        update_option(self::option_key($namespace), $v, false);
        return $v;
    }

    public static function set(string $namespace, int $version): void {
        update_option(self::option_key($namespace), max(1, $version), false);
    }
}
