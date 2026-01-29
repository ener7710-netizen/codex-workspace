<?php
declare(strict_types=1);

namespace SEOJusAI\Autopilot;

use SEOJusAI\Core\EmergencyStop;
use SEOJusAI\Core\ModuleRegistry;
use SEOJusAI\PageActions\PageActionExecutionService;
use SEOJusAI\Safety\SafeMode;

defined('ABSPATH') || exit;

/**
 * PageActionsStrategistRunner
 *
 * Шар: Autopilot/Strategy execution (server-side).
 *
 * Роль:
 * - НЕ показує кнопок у UI.
 * - Викликається стратегом (по крону) та застосовує ЛИШЕ allowlist дії
 *   з review_page_actions задач, якщо вони позначені auto_applicable і мають
 *   достатню впевненість.
 *
 * Інваріанти:
 * - не робить live Google API викликів
 * - не викликає Gemini/OpenAI
 * - застосовує тільки meta_title_update/meta_description_update (allowlist)
 */
final class PageActionsStrategistRunner {

    private const CRON_HOOK = 'seojusai/autopilot/page_actions/strategist_run';
    private const CRON_SCHEDULE = 'seojusai_twicemonthly';

    public function register(): void {
        add_action('init', [$this, 'ensure_schedule']);
        add_action(self::CRON_HOOK, [$this, 'run']);
    }

    public function ensure_schedule(): void {
        $modules = ModuleRegistry::instance();
        if (!$modules->is_enabled('autopilot')) {
            return;
        }

        if (!wp_next_scheduled(self::CRON_HOOK)) {
            // Запускаємо трохи пізніше за планувальник, щоб він встиг створити review tasks.
            wp_schedule_event(time() + 600, self::CRON_SCHEDULE, self::CRON_HOOK);
        }
    }

    public function run(): void {
        if (EmergencyStop::is_active()) {
            return;
        }
        if (class_exists(SafeMode::class) && SafeMode::is_enabled()) {
            return;
        }

        $modules = ModuleRegistry::instance();
        if (!$modules->is_enabled('autopilot')) {
            return;
        }

        $mode = (new AutopilotEngine())->get_mode();
        if ($mode !== 'full') {
            // У shadow/limited — стратег НЕ застосовує зміни.
            return;
        }

        $min_conf = (float) get_option('seojusai_page_actions_auto_confidence', 0.82);
        if ($min_conf <= 0) {
            $min_conf = 0.82;
        }

        $tasks = get_option('seojusai_tasks', []);
        if (!is_array($tasks) || empty($tasks)) {
            return;
        }

        $exec = new PageActionExecutionService();
        $changed = false;

        foreach ($tasks as $i => $task) {
            if (!is_array($task)) continue;

            if (($task['action'] ?? '') !== 'review_page_actions') {
                continue;
            }

            $status = (string) ($task['status'] ?? 'pending');
            if (in_array($status, ['applied', 'failed'], true)) {
                continue;
            }

            $post_id = (int) ($task['post_id'] ?? 0);
            if ($post_id <= 0) {
                continue;
            }

            $decision = is_array($task['decision'] ?? null) ? $task['decision'] : [];
            $actions = is_array($decision['actions'] ?? null) ? $decision['actions'] : [];
            if (empty($actions)) {
                continue;
            }

            $applied = [];
            $errors = [];

            foreach ($actions as $a) {
                if (!is_array($a)) continue;

                $type = sanitize_key((string)($a['type'] ?? ''));
                $auto = (bool) ($a['auto_applicable'] ?? $a['auto'] ?? false);
                $conf = is_numeric($a['confidence'] ?? null) ? (float) $a['confidence'] : 0.0;
                $value = isset($a['value']) ? (string) $a['value'] : '';

                if (!$auto || $conf < $min_conf) {
                    continue;
                }

                if (!in_array($type, ['meta_title_update', 'meta_description_update'], true)) {
                    continue;
                }
                if (trim($value) === '') {
                    // Немає executable value — пропускаємо.
                    continue;
                }

                $res = $exec->apply($post_id, $type, $value);
                if (!($res['ok'] ?? false)) {
                    $errors[] = [
                        'type' => $type,
                        'error' => (string) ($res['error'] ?? 'apply_failed'),
                    ];
                    continue;
                }

                $applied[] = [
                    'type' => $type,
                    'snapshot_id' => (int) ($res['snapshot_id'] ?? 0),
                ];
            }

            if (!empty($applied)) {
                $tasks[$i]['status'] = 'applied';
                $tasks[$i]['updated_at'] = time();
                $tasks[$i]['applied_actions'] = $applied;
                if (!empty($errors)) {
                    $tasks[$i]['apply_warnings'] = $errors;
                }
                $changed = true;
            } elseif (!empty($errors)) {
                $tasks[$i]['status'] = 'failed';
                $tasks[$i]['updated_at'] = time();
                $tasks[$i]['apply_errors'] = $errors;
                $changed = true;
            }
        }

        if ($changed) {
            update_option('seojusai_tasks', $tasks, false);
        }
    }
}
