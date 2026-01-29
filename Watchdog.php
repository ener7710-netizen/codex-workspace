<?php
declare(strict_types=1);

namespace SEOJusAI\Background;

use SEOJusAI\Core\EmergencyStop;
defined('ABSPATH') || exit;

/**
 * Watchdog (2026)
 *
 * Purpose:
 * - Detect missing recurring hooks (AS/WP-Cron) and re-create them
 * - Best-effort healing for background processing chain
 *
 * NOTE: This does not introspect internal AS DB tables (plugin-agnostic).
 */
final class Watchdog {

    private Scheduler $scheduler;

    public function __construct(?Scheduler $scheduler = null) {
        $this->scheduler = $scheduler ?? new Scheduler();
    }

    public function register(): void {

        // Run hourly (lightweight)
        add_action('init', function (): void {
            if (is_admin() && function_exists('wp_doing_ajax') && wp_doing_ajax()) {
                return;
            }
            $this->schedule_tick();
        }, 40);

        add_action('seojusai/background/watchdog_tick', [$this, 'tick']);
    }

    private function schedule_tick(): void {
        if (!wp_next_scheduled('seojusai/background/watchdog_tick')) {
            wp_schedule_event(time() + 180, 'hourly', 'seojusai/background/watchdog_tick');
        }
    }

    public function tick(): void {
        // Ensure recurring background hooks exist
        $this->scheduler->ensure_recurring();

        // Best-effort healing: re-queue stuck tasks
        $this->heal_stuck_tasks();

        // If too many recent failures — activate emergency stop
        $this->maybe_trigger_safe_mode();
    }

    private function heal_stuck_tasks(): void {
        if (EmergencyStop::is_active()) {
            return;
        }
        global $wpdb;
        $table = $wpdb->prefix . 'seojusai_tasks';

        // running for > 15 minutes => return to pending with backoff
        $stuck = $wpdb->get_results(
            "SELECT id, attempts FROM {$table} WHERE status = 'running' AND updated_at < (NOW() - INTERVAL 15 MINUTE) LIMIT 20",
            ARRAY_A
        );
        if (!$stuck) return;

        foreach ($stuck as $row) {
            $id = (int) ($row['id'] ?? 0);
            if ($id <= 0) continue;
            $attempts = (int) ($row['attempts'] ?? 0);
            $attempts++;

            $delay = class_exists('SEOJusAI\\Tasks\\RetryPolicy')
                ? \SEOJusAI\Tasks\RetryPolicy::next_delay($attempts)
                : 60;

            $available = gmdate('Y-m-d H:i:s', time() + $delay);

            $wpdb->update($table, [
                'status'       => 'pending',
                'attempts'     => $attempts,
                'available_at' => $available,
                'last_error'   => 'watchdog: task stuck >15m',
                'updated_at'   => current_time('mysql'),
            ], ['id' => $id]);
        }
    }

    private function maybe_trigger_safe_mode(): void {
        global $wpdb;
        $table = $wpdb->prefix . 'seojusai_tasks';

        // If 30+ failures in last hour => emergency stop
        $failed = (int) $wpdb->get_var("SELECT COUNT(1) FROM {$table} WHERE status = 'failed' AND updated_at >= (NOW() - INTERVAL 1 HOUR)");
        if ($failed >= 30) {
            EmergencyStop::activate();
        }
    }
}
