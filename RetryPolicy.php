<?php
declare(strict_types=1);

namespace SEOJusAI\Tasks;

defined('ABSPATH') || exit;

final class RetryPolicy {

    /**
     * Exponential backoff (seconds).
     */
    public static function next_delay(int $attempt): int {
        // attempt is 1-based after failure
        $base = 30; // 30s
        $max  = 3600; // 1h
        $delay = (int) min($max, $base * (2 ** max(0, $attempt - 1)));
        // jitter 0-15s
        $delay += random_int(0, 15);
        return $delay;
    }
}
