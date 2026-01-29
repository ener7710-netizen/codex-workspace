<?php
declare(strict_types=1);

namespace SEOJusAI\Autopilot;

use SEOJusAI\AI\PageActions\PageActionPlanner;
use SEOJusAI\Analytics\ObjectiveDatasetService;
use SEOJusAI\Core\EmergencyStop;
use SEOJusAI\Core\ModuleRegistry;
use SEOJusAI\Safety\SafeMode;

defined('ABSPATH') || exit;

/**
 * PageActionsAutopilotRunner
 *
 * Шар: Autopilot orchestration (поза AI).
 *
 * Роль:
 * - У shadow/limited/full режимах Autopilot формує *review tasks* для сторінок
 *   на основі об'єктивного датасету (GA4+GSC snapshots) та PageActionPlanner.
 *
 * Інваріанти:
 * - НЕ робить live Google API викликів (тільки snapshots через ObjectiveDatasetService).
 * - НЕ застосовує зміни (тільки enqueue review tasks).
 * - НЕ змінює існуючу логіку AutopilotEngine (аддитивно).
 */
final class PageActionsAutopilotRunner {

    private const CRON_HOOK = 'seojusai/autopilot/page_actions/run';
    private const OPTION_KEY = 'seojusai_autopilot_page_actions';
    private const CRON_SCHEDULE = 'seojusai_twicemonthly';

    public function register(): void {
        add_action('init', [$this, 'ensure_schedule']);
        add_filter('cron_schedules', [self::class, 'extend_cron_schedules']);
        add_action(self::CRON_HOOK, [$this, 'run']);
    }

    /**
     * Додаємо кастомний інтервал: ~2 рази на місяць (кожні 15 днів).
     *
     * @param array<string,array{interval:int,display:string}> $schedules
     * @return array<string,array{interval:int,display:string}>
     */
    public static function extend_cron_schedules(array $schedules): array {
        if (!isset($schedules[self::CRON_SCHEDULE])) {
            $schedules[self::CRON_SCHEDULE] = [
                'interval' => 60 * 60 * 24 * 15,
                'display'  => __('Двічі на місяць', 'seojusai'),
            ];
        }
        return $schedules;
    }

    public function ensure_schedule(): void {
        // Не плануємо, якщо Autopilot вимкнений у модульному реєстрі.
        $modules = ModuleRegistry::instance();
        if (!$modules->is_enabled('autopilot')) {
            return;
        }

        if (!wp_next_scheduled(self::CRON_HOOK)) {
            // 2 рази на місяць — стратегічна переоцінка (без шуму).
            wp_schedule_event(time() + 120, self::CRON_SCHEDULE, self::CRON_HOOK);
        }
    }

    public function run(): void {
        if (EmergencyStop::is_active()) {
            return;
        }
        if (class_exists(SafeMode::class) && SafeMode::is_enabled()) {
            // Safe Mode: не створюємо задачі.
            return;
        }

        $modules = ModuleRegistry::instance();
        if (!$modules->is_enabled('autopilot')) {
            return;
        }

        // Reliability pause gate
        if (class_exists(AutopilotReliability::class) && AutopilotReliability::is_paused()) {
            (new AutopilotLogRepository())->append([
                'event' => 'page_actions_paused_skip',
                'timestamp' => time(),
            ]);
            return;
        }

        $dataset = (new ObjectiveDatasetService())->build(120);
        $candidates = $this->select_candidates($dataset, 5);
        if (empty($candidates)) {
            return;
        }

        $mode = (new AutopilotEngine())->get_mode();
        $log = new AutopilotLogRepository();
        $planner = new PageActionPlanner();

        foreach ($candidates as $row) {
            $path = (string)($row['url'] ?? '');
            if ($path === '') {
                continue;
            }

            $post_id = $this->path_to_post_id($path);
            if ($post_id <= 0) {
                continue;
            }

            // Shadow: лише лог, без enqueue.
            if ($mode === 'shadow') {
                $log->append([
                    'event' => 'page_actions_shadow',
                    'timestamp' => time(),
                    'post_id' => $post_id,
                    'url' => $path,
                    'evidence' => $this->evidence_from_dataset($dataset),
                ]);
                continue;
            }

            // Планування (read-only).
            $planned = $planner->plan($path, 50);
            $actions = is_array($planned['actions'] ?? null) ? $planned['actions'] : [];
            if (empty($actions)) {
                continue;
            }

            $decision_hash = hash('sha256', (string)wp_json_encode([
                'url' => $path,
                'actions' => $actions,
                'evidence' => $this->evidence_from_dataset($dataset),
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

            $task = [
                'action'        => 'review_page_actions',
                'post_id'       => $post_id,
                'type'          => 'page_actions',
                'decision_hash' => $decision_hash,
                'decision'      => [
                    'url' => $path,
                    'actions' => $actions,
                    'page' => $planned['page'] ?? null,
                    'gemini_analytics' => $planned['gemini_analytics'] ?? null,
                    'evidence' => $this->evidence_from_dataset($dataset),
                ],
                'source'        => 'autopilot',
                'created_at'    => time(),
                'auto'          => false,
                'priority'      => 'medium',
            ];

			do_action('seojusai/tasks/enqueue', $task);
            do_action('seojusai/autopilot/task_enqueued', $task);

            $log->append([
                'event' => 'page_actions_enqueued',
                'timestamp' => time(),
                'post_id' => $post_id,
                'url' => $path,
                'actions_count' => count($actions),
                'decision_hash' => $decision_hash,
                'evidence' => $this->evidence_from_dataset($dataset),
            ]);
        }

        $this->mark_last_run($dataset);
    }

    /**
     * @param array<string,mixed> $dataset
     * @return array<int,array<string,mixed>>
     */
    private function select_candidates(array $dataset, int $limit): array {
        $limit = max(1, min(20, $limit));

        $pages = $dataset['merged_pages'] ?? $dataset['top_pages'] ?? [];
        if (!is_array($pages)) {
            return [];
        }

        // Проста, детермінована евристика (можна налаштувати пізніше):
        // - багато показів (>=500)
        // - низький CTR (< 1%)
        // - позиція 3..15
        // - високі відмови або низька залученість (якщо GA4 є)
        $out = [];
        foreach ($pages as $row) {
            if (!is_array($row)) continue;

            $url = (string)($row['url'] ?? '');
            if ($url === '' || $url === '/') continue;

            $gsc = is_array($row['gsc'] ?? null) ? $row['gsc'] : [];
            $ga4 = is_array($row['ga4'] ?? null) ? $row['ga4'] : [];

            $impr = (float)($gsc['impressions'] ?? 0);
            $ctr = (float)($gsc['ctr'] ?? 0);
            $pos = (float)($gsc['position'] ?? 0);

            $bounce = (float)($ga4['bounceRate'] ?? $ga4['bounce_rate'] ?? 0);
            $eng = (float)($ga4['engagementRate'] ?? $ga4['engagement_rate'] ?? 0);

            if ($impr < 500) continue;
            if ($ctr >= 0.01) continue;
            if ($pos < 3 || $pos > 15) continue;

            // Якщо GA4 метрики відсутні, все одно беремо (CTR/позиція вже “сигнал”).
            if ($bounce > 0 && $bounce < 0.55 && $eng > 0.45) {
                continue;
            }

            $out[] = $row;
            if (count($out) >= $limit) break;
        }

        return $out;
    }

    /** @param array<string,mixed> $dataset */
    private function evidence_from_dataset(array $dataset): array {
        $meta = is_array($dataset['meta'] ?? null) ? $dataset['meta'] : [];
        return [
            'ga4_snapshot_id' => $meta['ga4_snapshot_id'] ?? null,
            'gsc_snapshot_id' => $meta['gsc_snapshot_id'] ?? null,
            'generated_at' => $dataset['generated_at'] ?? null,
        ];
    }

    private function path_to_post_id(string $path): int {
        $path = ltrim($path, '/');
        $url = home_url('/' . $path);
        $id = (int) url_to_postid($url);
        return $id > 0 ? $id : 0;
    }

    /** @param array<string,mixed> $dataset */
    private function mark_last_run(array $dataset): void {
        $opt = get_option(self::OPTION_KEY, []);
        if (!is_array($opt)) $opt = [];
        $opt['last_run'] = time();
        $opt['last_evidence'] = $this->evidence_from_dataset($dataset);
        update_option(self::OPTION_KEY, $opt, false);
    }
}
