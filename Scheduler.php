<?php
declare(strict_types=1);

namespace SEOJusAI\Background;

defined('ABSPATH') || exit;

final class Scheduler {

    public const HOOK_RUN_WORKER = 'seojusai/background/run_worker';
    public const HOOK_LEARNING   = 'seojusai/background/learning_tick';
    public const HOOK_AUTOPILOT_HEALTH = 'seojusai/background/autopilot_health_tick';
    public const HOOK_AUDIT_REFRESH_POST = 'seojusai/background/audit_refresh_post';

    public function register(): void {
        add_action(self::HOOK_RUN_WORKER, [ $this, 'run_worker' ]);
        add_action(self::HOOK_LEARNING, [ $this, 'learning_tick' ]);
        add_action(self::HOOK_AUTOPILOT_HEALTH, [ $this, 'autopilot_health_tick' ]);
        add_action(self::HOOK_AUDIT_REFRESH_POST, [ $this, 'audit_refresh_post' ], 10, 1);

        add_action('init', function (): void {
            $this->ensure_recurring();
        }, 30);
    }


    public function ensure_recurring(): void {

        $has_as = function_exists('as_schedule_recurring_action')
            && function_exists('as_unschedule_all_actions')
            && function_exists('as_next_scheduled_action');

        $backend = (string) get_option('seojusai_scheduler_backend', '');

        if ($backend !== 'as' && $backend !== 'wpcron') {
            $backend = $has_as ? 'as' : 'wpcron';
            update_option('seojusai_scheduler_backend', $backend, false);
        }

        // Якщо Action Scheduler доступний — використовуємо його як єдиний бекенд (без дублювання з WP-Cron)
        if ($backend === 'as' && $has_as) {

            // прибираємо WP-Cron дублікати
            wp_clear_scheduled_hook(self::HOOK_RUN_WORKER);
            wp_clear_scheduled_hook(self::HOOK_LEARNING);
            wp_clear_scheduled_hook(self::HOOK_AUTOPILOT_HEALTH);

            // ставимо recurring задачі в AS (захист від дублювань)
            if (!as_next_scheduled_action(self::HOOK_RUN_WORKER, [], 'seojusai')) {
                as_schedule_recurring_action(time() + 60, 300, self::HOOK_RUN_WORKER, [], 'seojusai');
            }
            if (!as_next_scheduled_action(self::HOOK_LEARNING, [], 'seojusai')) {
                // weekly learning tick
                as_schedule_recurring_action(time() + 300, 7 * DAY_IN_SECONDS, self::HOOK_LEARNING, [], 'seojusai');
            }
            if (!as_next_scheduled_action(self::HOOK_AUTOPILOT_HEALTH, [], 'seojusai')) {
                as_schedule_recurring_action(time() + 120, HOUR_IN_SECONDS, self::HOOK_AUTOPILOT_HEALTH, [], 'seojusai');
            }

            return;
        }

        // WP-Cron backend
        if (!wp_next_scheduled(self::HOOK_RUN_WORKER)) {
            wp_schedule_event(time() + 60, 'five_minutes', self::HOOK_RUN_WORKER);
        }
        if (!wp_next_scheduled(self::HOOK_LEARNING)) {
            wp_schedule_event(time() + 300, 'weekly', self::HOOK_LEARNING);
        }
        if (!wp_next_scheduled(self::HOOK_AUTOPILOT_HEALTH)) {
            wp_schedule_event(time() + 120, 'hourly', self::HOOK_AUTOPILOT_HEALTH);
        }
    }


    public function clear(): void {
        wp_clear_scheduled_hook(self::HOOK_RUN_WORKER);
        wp_clear_scheduled_hook(self::HOOK_LEARNING);
            wp_clear_scheduled_hook(self::HOOK_AUTOPILOT_HEALTH);

        if (function_exists('as_unschedule_all_actions')) {
            as_unschedule_all_actions(self::HOOK_RUN_WORKER, [], 'seojusai');
            as_unschedule_all_actions(self::HOOK_LEARNING, [], 'seojusai');
                as_unschedule_all_actions(self::HOOK_AUTOPILOT_HEALTH, [], 'seojusai');
        }
    }

    public function run_worker(): void {
        if (class_exists('SEOJusAI\\Tasks\\TaskWorker')) {
            (new \SEOJusAI\Tasks\TaskWorker())->run_once();
        }
    }

    public function autopilot_health_tick(): void {
        if (class_exists('SEOJusAI\\Autopilot\\AutopilotReliabilityMonitor')) {
            (new \SEOJusAI\Autopilot\AutopilotReliabilityMonitor())->tick();
        }
    }

    public function learning_tick(): void {
        if (class_exists('SEOJusAI\\Learning\\LearningLoop')) {
            (new \SEOJusAI\Learning\LearningLoop())->run_weekly();
        }
    }

    /**
     * Action Scheduler job: refresh page audit summary (front snapshot + counters).
     */
    public function audit_refresh_post($args = []): void {
        $post_id = 0;
        if (is_array($args) && isset($args['post_id'])) {
            $post_id = (int) $args['post_id'];
        } elseif (is_numeric($args)) {
            $post_id = (int) $args;
        }
        if ($post_id <= 0) {
            return;
        }
        if (class_exists('SEOJusAI\\Tasks\\AuditPostJob')) {
            \SEOJusAI\Tasks\AuditPostJob::run($post_id);
        }
    }
}
