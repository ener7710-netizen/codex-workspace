<?php
declare(strict_types=1);

namespace SEOJusAI\Tasks;

defined('ABSPATH') || exit;

final class ActionSchedulerBridge {

    public const HOOK_RUN_TASK = 'seojusai/tasks/run';

    public static function available(): bool {
        return function_exists('as_schedule_single_action') && function_exists('as_enqueue_async_action');
    }

    public static function schedule(int $task_id, int $timestamp, string $group='seojusai'): bool {
        if (!self::available()) return false;
        // schedule single action; unique args prevents duplicates for same task_id
        try {
            as_schedule_single_action($timestamp, self::HOOK_RUN_TASK, ['task_id' => $task_id], $group);
            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    public static function enqueue_now(int $task_id, string $group='seojusai'): bool {
        if (!self::available()) return false;
        try {
            // async action runs asap
            as_enqueue_async_action(self::HOOK_RUN_TASK, ['task_id' => $task_id], $group);
            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }
}
