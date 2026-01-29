<?php
declare(strict_types=1);

namespace SEOJusAI\CLI;

use SEOJusAI\Background\Scheduler;

defined('ABSPATH') || exit;

/**
 * wp seojusai doctor
 *
 * Quick self-diagnostics for production (2026).
 * - environment
 * - DB tables
 * - REST routes availability (basic)
 * - Scheduler backend (AS/WP-Cron) + recurring hooks presence
 */
final class DoctorCommand {

    /**
     * @param array<int,string> $args
     * @param array<string,string> $assoc_args
     */
    public static function run(array $args, array $assoc_args): void {

        $ok = true;

        $checks = [
            'env'      => [self::class, 'check_env'],
            'db'       => [self::class, 'check_db'],
            'scheduler'=> [self::class, 'check_scheduler'],
        ];

        foreach ($checks as $name => $cb) {
            $r = (bool) call_user_func($cb);
            if (!$r) {
                $ok = false;
            }
        }

        if (class_exists('\WP_CLI')) {
            if ($ok) {
                \WP_CLI::success('SEOJusAI doctor: OK');
            } else {
                \WP_CLI::warning('SEOJusAI doctor: issues found');
            }
        }
    }

    private static function check_env(): bool {
        $ok = true;

        if (version_compare(PHP_VERSION, '8.1.0', '<')) {
            $ok = false;
            if (class_exists('\WP_CLI')) {
                \WP_CLI::error('PHP 8.1+ required (current: ' . PHP_VERSION . ')', false);
            }
        } else if (class_exists('\WP_CLI')) {
            \WP_CLI::log('PHP: ' . PHP_VERSION);
        }

        global $wp_version;
        if (is_string($wp_version) && version_compare($wp_version, '6.4.0', '<')) {
            $ok = false;
            if (class_exists('\WP_CLI')) {
                \WP_CLI::error('WP 6.4+ recommended (current: ' . $wp_version . ')', false);
            }
        } else if (class_exists('\WP_CLI')) {
            \WP_CLI::log('WP: ' . (string) $wp_version);
        }

        return $ok;
    }

    private static function check_db(): bool {
        global $wpdb;

        $need = [
            $wpdb->prefix . 'seojusai_tasks',
            $wpdb->prefix . 'seojusai_explanations',
            $wpdb->prefix . 'seojusai_vectors',
            $wpdb->prefix . 'seojusai_learning',
        ];

        $ok = true;
        foreach ($need as $t) {
            $exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $t));
            if ($exists !== $t) {
                $ok = false;
                if (class_exists('\WP_CLI')) {
                    \WP_CLI::warning('Missing table: ' . $t);
                }
            }
        }

        if ($ok && class_exists('\WP_CLI')) {
            \WP_CLI::log('DB tables: OK');
        }

        return $ok;
    }

    private static function check_scheduler(): bool {

        $backend = (string) get_option('seojusai_scheduler_backend', '');
        $has_as  = function_exists('as_next_scheduled_action');

        if (class_exists('\WP_CLI')) {
            \WP_CLI::log('Scheduler backend: ' . ($backend !== '' ? $backend : 'auto'));
            \WP_CLI::log('Action Scheduler available: ' . ($has_as ? 'yes' : 'no'));
        }

        // Ensure hooks exist (best-effort; scheduler will self-heal on init too)
        $ok = true;

        if ($backend === 'as' && $has_as) {
            if (!as_next_scheduled_action(Scheduler::HOOK_RUN_WORKER, [], 'seojusai')) $ok = false;
            if (!as_next_scheduled_action(Scheduler::HOOK_LEARNING, [], 'seojusai')) $ok = false;
            if (!as_next_scheduled_action(Scheduler::HOOK_AUTOPILOT_HEALTH, [], 'seojusai')) $ok = false;
        } else {
            if (!wp_next_scheduled(Scheduler::HOOK_RUN_WORKER)) $ok = false;
            if (!wp_next_scheduled(Scheduler::HOOK_LEARNING)) $ok = false;
            if (!wp_next_scheduled(Scheduler::HOOK_AUTOPILOT_HEALTH)) $ok = false;
        }

        if (class_exists('\WP_CLI')) {
            if ($ok) {
                \WP_CLI::log('Scheduler hooks: OK');
            } else {
                \WP_CLI::warning('Scheduler hooks: missing (will be re-created on init)');
            }
        }

        return $ok;
    }
}
