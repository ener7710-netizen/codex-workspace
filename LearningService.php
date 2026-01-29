<?php
declare(strict_types=1);

namespace SEOJusAI\Learning;

use SEOJusAI\Tasks\TaskQueue;

defined('ABSPATH') || exit;

final class LearningService {

    public const TASK_EVALUATE = 'learning/evaluate';

    public static function enabled(): bool {
        return (bool) get_option('seojusai_learning_enabled', true);
    }

    public static function observe_days(): int {
        $d = (int) get_option('seojusai_learning_observe_days', 7);
        return max(1, min(60, $d));
    }

    public static function register(): void {
        add_action('seojusai/decision/applied', [self::class, 'on_applied'], 10, 1);
    }

    /**
     * @param array<string,mixed> $ctx
     */
    public static function on_applied(array $ctx): void {
        if (!self::enabled()) return;

        $hash = isset($ctx['decision_hash']) ? sanitize_text_field((string)$ctx['decision_hash']) : '';
        if ($hash === '') return;

        $entity_type = isset($ctx['entity_type']) ? sanitize_key((string)$ctx['entity_type']) : 'post';
        $entity_id   = isset($ctx['entity_id']) ? (int)$ctx['entity_id'] : 0;

        $repo = new LearningEventRepository();
        // collect before metrics
        $collector = new PostOutcomeCollector();
        $before = $collector->before($entity_type, $entity_id);

        $days = self::observe_days();

        $id = $repo->create([
            'decision_hash' => $hash,
            'module_slug' => sanitize_key((string)($ctx['module_slug'] ?? '')),
            'action_slug' => sanitize_key((string)($ctx['action_slug'] ?? '')),
            'entity_type' => $entity_type,
            'entity_id' => $entity_id,
            'predicted_roi' => (float)($ctx['predicted_roi'] ?? 0),
            'predicted_impact' => (float)($ctx['predicted_impact'] ?? 0),
            'predicted_risk' => (string)($ctx['predicted_risk'] ?? 'low'),
            'confidence' => (float)($ctx['confidence'] ?? 0),
            'applied_at' => current_time('mysql', true),
            'observe_after' => gmdate('Y-m-d H:i:s', time() + $days * DAY_IN_SECONDS),
            'status' => 'scheduled',
        ]);

        if ($id <= 0) return;

        $repo->set_before_metrics($id, $before);

        // schedule evaluation task
        $queue = new TaskQueue();
        $key = 'learning:evaluate:' . $hash;
        $queue->enqueue(self::TASK_EVALUATE, [
            'learning_id' => $id,
            'decision_hash' => $hash,
            'entity_type' => $entity_type,
            'entity_id' => $entity_id,
        ], $key);
    }
}
