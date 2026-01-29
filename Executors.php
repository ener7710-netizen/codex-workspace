<?php
declare(strict_types=1);

namespace SEOJusAI\Tasks;

use SEOJusAI\Vectors\VectorIndexManager;
use SEOJusAI\Vectors\VectorRebuilder;
use SEOJusAI\Vectors\VectorRebuildState;
use SEOJusAI\Vectors\VectorNamespaces;
use SEOJusAI\Vectors\VectorStore;
use SEOJusAI\Learning\LearningService;
use SEOJusAI\Learning\LearningEventRepository;
use SEOJusAI\Learning\PostOutcomeCollector;

defined('ABSPATH') || exit;

final class Executors {

    public static function register(): void {
        add_filter('seojusai/tasks/execute', [self::class, 'execute'], 10, 4);
    }

    /**
     * @param bool $handled
     * @param string $action
     * @param array $payload
     * @param array $taskRow
     */
    public static function execute($handled, string $action, array $payload, array $taskRow): bool {
        // If already handled by another executor, respect it
        if ($handled === true) return true;

        switch ($action) {
            case VectorRebuilder::TASK_INDEX_POST:
                return self::vectors_index_post($payload);

            case VectorRebuilder::TASK_REBUILD_BATCH:
                return self::vectors_rebuild_batch($payload);

            case LearningService::TASK_EVALUATE:
                return self::learning_evaluate($payload);

			default:
				// Not handled here
				return false;
        }
    }

    private static function vectors_index_post(array $payload): bool {
        $post_id = isset($payload['post_id']) ? (int)$payload['post_id'] : 0;
        if ($post_id <= 0) return true; // nothing to do

        $ns = isset($payload['namespace']) ? sanitize_key((string)$payload['namespace']) : VectorNamespaces::POSTS;
        $version = isset($payload['version']) ? (int)$payload['version'] : null;

        $res = VectorIndexManager::index_post($post_id, $ns, $version);

        return !empty($res['ok']);
    }

    private static function vectors_rebuild_batch(array $payload): bool {
        $ns = isset($payload['namespace']) ? sanitize_key((string)$payload['namespace']) : VectorNamespaces::POSTS;
        $version = isset($payload['version']) ? max(1, (int)$payload['version']) : null;
        $offset = isset($payload['offset']) ? max(0, (int)$payload['offset']) : 0;
        $batch = isset($payload['batch_size']) ? max(5, min(100, (int)$payload['batch_size'])) : 20;

        $state = VectorRebuildState::get();
        if (empty($state) || ($state['namespace'] ?? '') !== $ns) {
            // initialize state if missing
            VectorRebuildState::set([
                'namespace' => $ns,
                'version' => $version ?? 1,
                'batch_size' => $batch,
                'offset' => $offset,
                'started_at' => current_time('mysql', true),
                'updated_at' => current_time('mysql', true),
                'done' => false,
                'indexed' => 0,
            ]);
            $state = VectorRebuildState::get();
        }

        $version = $version ?? (int)($state['version'] ?? 1);

        // Fetch published post IDs in batches
        $q = new \WP_Query([
            'post_type' => 'any',
            'post_status' => 'publish',
            'fields' => 'ids',
            'posts_per_page' => $batch,
            'offset' => $offset,
            'no_found_rows' => true,
            'orderby' => 'ID',
            'order' => 'ASC',
        ]);

        $ids = is_array($q->posts) ? $q->posts : [];
        $indexed = 0;

        foreach ($ids as $pid) {
            $res = VectorIndexManager::index_post((int)$pid, $ns, $version);
            if (!empty($res['ok'])) $indexed += (int)($res['indexed'] ?? 0) > 0 ? 1 : 0;
        }

        $state['indexed'] = (int)($state['indexed'] ?? 0) + $indexed;
        $state['offset'] = $offset + count($ids);
        $state['updated_at'] = current_time('mysql', true);

        if (count($ids) < $batch) {
            $state['done'] = true;
            $state['finished_at'] = current_time('mysql', true);

            // Optional cleanup: purge previous versions to save DB (keep current only)
            $store = new VectorStore();
            // purge everything except current version for this namespace
            global $wpdb;
            $table = $wpdb->prefix . 'seojusai_vectors';
            $wpdb->query($wpdb->prepare("DELETE FROM {$table} WHERE namespace=%s AND vector_version <> %d", $ns, $version));
        } else {
            // enqueue next batch
            $next_offset = $offset + $batch;
            $queue = new TaskQueue();
            $key = 'vectors:rebuild:' . $ns . ':' . $version . ':' . $next_offset;
            $queue->enqueue(VectorRebuilder::TASK_REBUILD_BATCH, [
                'namespace' => $ns,
                'version' => $version,
                'offset' => $next_offset,
                'batch_size' => $batch,
                'priority' => 'high',
                'max_attempts' => 3,
                'source' => 'system',
            ], $key);
        }

        VectorRebuildState::set($state);

        return true;
    }


private static function learning_evaluate(array $payload): bool {
    $id = isset($payload['learning_id']) ? (int)$payload['learning_id'] : 0;
    $hash = isset($payload['decision_hash']) ? sanitize_text_field((string)$payload['decision_hash']) : '';
    if ($id <= 0 || $hash === '') return true;

    $repo = new LearningEventRepository();
    $row = $repo->get_by_hash($hash);
    if (!$row) return true;

    // not yet due
    $observe_after = (string)($row['observe_after'] ?? '');
    if ($observe_after && strtotime($observe_after) > time()) {
        // reschedule once (in case of early run)
        $repo->reschedule((int)$row['id'], 1);
        return true;
    }

    $entity_type = sanitize_key((string)($row['entity_type'] ?? 'post'));
    $entity_id = (int)($row['entity_id'] ?? 0);

    $collector = new PostOutcomeCollector();
    $before = is_array($row['before_metrics'] ?? null) ? (array)$row['before_metrics'] : [];
    $after = $collector->after($entity_type, $entity_id);
    $diff = $collector->diff($before, $after);

    $outcome = [
        'diff' => $diff,
        'notes' => [
            'collector' => 'post',
            'observed_days' => (int) get_option('seojusai_learning_observe_days', 7),
            'has_gsc' => (bool) get_option('seojusai_gsc_enabled', false),
        ],
    ];

    $repo->mark_observed((int)$row['id'], $after, $outcome, 'observed');

    // also store into Explain (optional) via action for future extension
    do_action('seojusai/learning/observed', [
        'decision_hash' => $hash,
        'learning_id' => (int)$row['id'],
        'entity_type' => $entity_type,
        'entity_id' => $entity_id,
        'outcome' => $outcome,
            'event' => $row,
    ]);

    return true;
}

}
