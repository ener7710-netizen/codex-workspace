<?php
declare(strict_types=1);

namespace SEOJusAI\Vectors;

use SEOJusAI\Tasks\TaskQueue;

defined('ABSPATH') || exit;

final class VectorRebuilder {

    public const TASK_REBUILD_BATCH = 'vectors/rebuild_batch';
    public const TASK_INDEX_POST    = 'vectors/index_post';

    public static function start(string $namespace = VectorNamespaces::POSTS, int $batch_size = 20): array {
        $namespace = sanitize_key($namespace ?: VectorNamespaces::DEFAULT);
        $batch_size = max(5, min(100, (int)$batch_size));

        // bump version -> new namespace version becomes active
        $new_version = VectorVersion::bump($namespace);

        // reset state
        VectorRebuildState::set([
            'namespace' => $namespace,
            'version' => $new_version,
            'batch_size' => $batch_size,
            'offset' => 0,
            'started_at' => current_time('mysql', true),
            'updated_at' => current_time('mysql', true),
            'done' => false,
            'indexed' => 0,
        ]);

        $q = new TaskQueue();
        $task_id = $q->enqueue(self::TASK_REBUILD_BATCH, [
            'namespace' => $namespace,
            'version' => $new_version,
            'offset' => 0,
            'batch_size' => $batch_size,
            'priority' => 'high',
            'max_attempts' => 3,
            'source' => 'system',
        ], 'vectors:rebuild:' . $namespace . ':' . $new_version . ':0');

        return ['ok'=> (bool)$task_id, 'namespace'=>$namespace, 'version'=>$new_version, 'task_id'=>$task_id];
    }

    public static function schedule_index_post(int $post_id, string $namespace = VectorNamespaces::POSTS): void {
        $post_id = (int)$post_id;
        if ($post_id <= 0) return;

        $namespace = sanitize_key($namespace ?: VectorNamespaces::DEFAULT);
        $version = VectorVersion::current($namespace);

        $q = new TaskQueue();
        // key ensures de-dup per post
        $key = 'vectors:index:' . $namespace . ':' . $version . ':post:' . $post_id;
        $q->enqueue(self::TASK_INDEX_POST, [
            'post_id' => $post_id,
            'namespace' => $namespace,
            'version' => $version,
            'priority' => 'medium',
            'max_attempts' => 5,
            'source' => 'system',
        ], $key);
    }
}
