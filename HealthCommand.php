<?php
declare(strict_types=1);

namespace SEOJusAI\CLI;

use SEOJusAI\Health\HealthService;

defined('ABSPATH') || exit;

final class HealthCommand {

    /**
     * WP-CLI: wp seojusai health
     *
     * @param array $args
     * @param array $assoc_args
     */
    public static function run(array $args, array $assoc_args): void {
        if (!class_exists(HealthService::class)) {
            \WP_CLI::error('HealthService недоступний.');
            return;
        }

        $checks = HealthService::checks();
        $fail = 0; $warn = 0;

        foreach ($checks as $c) {
            $status = (string)($c['status'] ?? 'info');
            $title  = (string)($c['title'] ?? '');
            $msg    = (string)($c['message'] ?? '');

            if ($status === 'fail') { $fail++; \WP_CLI::log('✖ ' . $title . ': ' . $msg); }
            elseif ($status === 'warn') { $warn++; \WP_CLI::log('! ' . $title . ': ' . $msg); }
            else { \WP_CLI::log('• ' . $title . ': ' . $msg); }
        }

        if ($fail > 0) {
            \WP_CLI::error("Виявлено критичні проблеми: {$fail}. Попереджень: {$warn}.");
            return;
        }

        if ($warn > 0) {
            \WP_CLI::warning("Готово з попередженнями: {$warn}.");
            return;
        }

        \WP_CLI::success('Усе виглядає стабільно.');
    }
}
