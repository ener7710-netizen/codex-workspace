<?php
declare(strict_types=1);

namespace SEOJusAI\Tasks;

use SEOJusAI\Core\EmergencyStop;
use SEOJusAI\Autopilot\AutopilotReliability;
use SEOJusAI\Audit\AuditLogger;
use SEOJusAI\Repository\DecisionRepository;

defined('ABSPATH') || exit;

final class TaskRunner {

    private TaskQueue $queue;

    public function __construct(?TaskQueue $queue=null) {
        $this->queue = $queue ?? new TaskQueue();
    }

    public function register(): void {
        add_action(ActionSchedulerBridge::HOOK_RUN_TASK, [$this, 'run_action'], 10, 1);
    }

    public function run_action($task_id): void {
        $task_id = (int) $task_id;
        if ($task_id <= 0) return;
        if (EmergencyStop::is_active()) return;

        // Concurrency guard
        $limit = (int) get_option('seojusai_tasks_concurrency', 5);
        if ($limit < 1) $limit = 1;
        if ($this->queue->count_running() >= $limit) {
            // reschedule shortly
            $this->queue->reschedule($task_id, time() + 60, 'concurrency_limit');
            ActionSchedulerBridge::schedule($task_id, time() + 60);
            return;
        }

        // Reserve by id (atomic)
        $task = $this->queue->reserve_by_id($task_id);
        if (!$task) return;


// Autopilot guard rails (only for auto tasks)
$payload = is_array($task['payload'] ?? null) ? (array)$task['payload'] : [];
$is_auto = !empty($payload['auto'] ?? false);
$post_id = (int)($payload['post_id'] ?? $task['post_id'] ?? 0);

// If autopilot reliability is paused, do not auto-execute.
if ($is_auto && class_exists(AutopilotReliability::class) && AutopilotReliability::is_paused()) {
    $this->queue->reschedule($task_id, time() + 600, 'autopilot_paused');
    ActionSchedulerBridge::schedule($task_id, time() + 600);
    return;
}

// Manual lock per post: if enabled, never auto-apply tasks for this post.
if ($is_auto && $post_id > 0) {
    $locked = (bool) get_post_meta($post_id, 'seojusai_manual_lock', true);
    if ($locked) {
            if (!empty($task['decision_hash'])) {
                DecisionRepository::mark_cancelled((string)$task['decision_hash'], 'Manual lock enabled');
            }
        $this->queue->reschedule($task_id, time() + 3600, 'manual_lock');
        $this->queue->set_last_error($task_id, 'manual_lock: auto execution blocked');
        ActionSchedulerBridge::schedule($task_id, time() + 3600);
        return;
    }
}

// Rate limit: max auto tasks per minute (global) and per post per hour.
if ($is_auto) {
    $max_per_min = (int) get_option('seojusai_autopilot_max_auto_per_minute', 3);
    if ($max_per_min < 1) $max_per_min = 1;

    $bucket = gmdate('YmdHi'); // UTC minute bucket
    $k = 'seojusai_ap_auto_' . $bucket;
    $count = (int) get_transient($k);
    if ($count >= $max_per_min) {
        $this->queue->reschedule($task_id, time() + 120, 'auto_rate_limited');
        ActionSchedulerBridge::schedule($task_id, time() + 120);
        return;
    }
    set_transient($k, $count + 1, 120);

    if ($post_id > 0) {
        $max_post_hr = (int) get_option('seojusai_autopilot_max_auto_per_post_hour', 2);
        if ($max_post_hr < 1) $max_post_hr = 1;

        $bucket_hr = gmdate('YmdH'); // UTC hour bucket
        $kp = 'seojusai_ap_auto_post_' . $post_id . '_' . $bucket_hr;
        $pc = (int) get_transient($kp);
        if ($pc >= $max_post_hr) {
            $this->queue->reschedule($task_id, time() + 900, 'auto_post_rate_limited');
            ActionSchedulerBridge::schedule($task_id, time() + 900);
            return;
        }
        set_transient($kp, $pc + 1, 3700);
    }
}

        $ok = false;
        try {
            $ok = (bool) apply_filters('seojusai/tasks/execute', false, (string)$task['action'], (array)$task['payload'], $task);
        } catch (\Throwable $e) {
            $ok = false;
            $this->queue->set_last_error($task_id, $e->getMessage());
        }

        if ($ok) {
            $this->queue->complete($task_id);
            return;
        }

        // Fail + retry or DLQ

// Autopilot safety: pause on burst failures (auto tasks only)
try {
    $payload = is_array($task['payload'] ?? null) ? (array)$task['payload'] : [];
    $is_auto = !empty($payload['auto'] ?? false);
    if ($is_auto && class_exists(AutopilotReliability::class)) {
        $bucket10 = gmdate('YmdHi', (int)(time() / 600) * 600); // 10-minute bucket
        $kf = 'seojusai_ap_fail_' . $bucket10;
        $fc = (int) get_transient($kf);
        $fc++;
        set_transient($kf, $fc, 1200);

        $thr = (int) get_option('seojusai_autopilot_fail_burst_threshold', 5);
        if ($thr < 3) $thr = 3;

        if ($fc >= $thr && !AutopilotReliability::is_paused()) {
            AutopilotReliability::pause('fail_burst', ['count' => $fc, 'bucket' => $bucket10]);
        }
    }
} catch (\Throwable $e) {
    // ignore guard rail errors
}

        AuditLogger::log($task['decision_hash'] ?? '', 'post', (int)($task['post_id'] ?? 0), 'task_failed', 'Task execution failed', ['task'=>$task]);
        $this->queue->fail_and_maybe_retry($task_id);
    }
}
