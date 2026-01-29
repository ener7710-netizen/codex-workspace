<?php
declare(strict_types=1);

namespace SEOJusAI\AI;

defined('ABSPATH') || exit;

final class BaselineProvider {

    public static function get(string $taxonomy): array {
        $key = 'seojusai_baseline_' . $taxonomy;
        $cached = get_transient($key);
        if ($cached) {
            return $cached;
        }

        // Neutral prior: uniform distribution over labels
        $labels = require SEOJUSAI_PATH . 'src/Config/taxonomies.php';
        $count = count($labels[$taxonomy] ?? []);
        if ($count === 0) return [];

        $baseline = [];
        foreach (array_keys($labels[$taxonomy]) as $label) {
            $baseline[$label] = 1 / $count;
        }

        set_transient($key, $baseline, DAY_IN_SECONDS);
        return $baseline;
    }
}
