<?php
declare(strict_types=1);

namespace SEOJusAI\PageActions;

use SEOJusAI\Meta\MetaRepository;
use SEOJusAI\Snapshots\SnapshotService;

defined('ABSPATH') || exit;

/**
 * PageActionExecutionService
 *
 * Шар: Execution (але викликається ТІЛЬКИ з адмін-контексту).
 *
 * Відповідальність:
 * - застосувати окрему дію до конкретної сторінки (тільки allowlist)
 * - перед змінами зробити post snapshot (rollback гарантія)
 * - зберегти diff (old/new) для title/description
 *
 * Інваріанти:
 * - не викликає Gemini/OpenAI
 * - не робить live Google API запитів
 * - не запускає Autopilot
 */
final class PageActionExecutionService {

    public const LOG_META_KEY = '_seojusai_page_action_log';

    private SnapshotService $snapshots;
    private MetaRepository $meta;

    public function __construct(?SnapshotService $snapshots = null, ?MetaRepository $meta = null) {
        $this->snapshots = $snapshots ?? new SnapshotService();
        $this->meta = $meta ?? new MetaRepository();
    }

    /**
     * Apply a single page action.
     *
     * Supported types:
     * - meta_title_update
     * - meta_description_update
     *
     * @return array{ok:bool,error?:string,snapshot_id?:int,diff?:array<string,mixed>}
     */
    public function apply(int $post_id, string $type, string $new_value): array {
        $post_id = (int) $post_id;
        $type = sanitize_key($type);
        $new_value = trim(wp_kses_post($new_value));

        if ($post_id <= 0 || $new_value === '') {
            return ['ok' => false, 'error' => 'invalid_input'];
        }

        if (!in_array($type, ['meta_title_update', 'meta_description_update'], true)) {
            return ['ok' => false, 'error' => 'unsupported_action'];
        }

        // 1) Capture snapshot for rollback.
        $snapshot_id = $this->snapshots->capture_post($post_id, 'page_action', [
            'action_type' => $type,
            'user_id' => (int) get_current_user_id(),
        ]);
        if ($snapshot_id <= 0) {
            return ['ok' => false, 'error' => 'snapshot_failed'];
        }

        // 2) Read old value.
        $current = $this->meta->get($post_id);
        $field = $type === 'meta_title_update' ? 'title' : 'description';
        $old_value = (string) ($current[$field] ?? '');

        // 3) Apply change.
        $payload = [
            'title' => $current['title'] ?? '',
            'description' => $current['description'] ?? '',
        ];
        $payload[$field] = $new_value;

        $this->meta->save($post_id, $payload);

        // 4) Persist diff for quick UI + audit.
        $diff = [
            'type' => $type,
            'field' => $field,
            'old' => $old_value,
            'new' => $new_value,
            'snapshot_id' => $snapshot_id,
            'applied_at' => time(),
            'applied_by' => (int) get_current_user_id(),
        ];

        $this->append_log($post_id, $diff);

        return ['ok' => true, 'snapshot_id' => $snapshot_id, 'diff' => $diff];
    }

    /**
     * Rollback via snapshot id.
     *
     * @return array{ok:bool,error?:string}
     */
    public function rollback(int $snapshot_id): array {
        $snapshot_id = (int) $snapshot_id;
        if ($snapshot_id <= 0) {
            return ['ok' => false, 'error' => 'invalid_snapshot'];
        }
        $res = $this->snapshots->restore_post_snapshot($snapshot_id);
        return is_wp_error($res) ? ['ok' => false, 'error' => 'rollback_failed'] : ['ok' => true];
    }

    /**
     * @param array<string,mixed> $entry
     */
    private function append_log(int $post_id, array $entry): void {
        $log = get_post_meta($post_id, self::LOG_META_KEY, true);
        $log = is_array($log) ? $log : [];
        array_unshift($log, $entry);
        // keep last 50
        $log = array_slice($log, 0, 50);
        update_post_meta($post_id, self::LOG_META_KEY, $log);
    }
}
