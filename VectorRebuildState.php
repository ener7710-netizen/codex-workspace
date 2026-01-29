<?php
declare(strict_types=1);

namespace SEOJusAI\Vectors;

defined('ABSPATH') || exit;

final class VectorRebuildState {

    private const OPTION = 'seojusai_vectors_rebuild_state';

    /** @return array<string,mixed> */
    public static function get(): array {
        $v = get_option(self::OPTION, []);
        return is_array($v) ? $v : [];
    }

    public static function set(array $state): void {
        update_option(self::OPTION, $state, false);
    }

    public static function reset(): void {
        delete_option(self::OPTION);
    }
}
