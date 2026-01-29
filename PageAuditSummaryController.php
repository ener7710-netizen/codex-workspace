<?php
declare(strict_types=1);

namespace SEOJusAI\Rest\Controllers;

use WP_REST_Request;
use WP_REST_Response;
use SEOJusAI\Analyze\PageAuditSummary;
use SEOJusAI\Input\Input;
use SEOJusAI\Rest\AbstractRestController;
use SEOJusAI\Rest\Contracts\RestControllerInterface;
use SEOJusAI\Rest\RestKernel;
use SEOJusAI\Tasks\TaskQueue;

defined('ABSPATH') || exit;

/**
 * PageAuditSummaryController
 * Fast, UI-ready summary for a single post (counts + issues).
 */
final class PageAuditSummaryController extends AbstractRestController implements RestControllerInterface {

    public function register_routes(): void {
        register_rest_route('seojusai/v1', '/page-audit-summary', [
            'methods'             => 'POST',
            'permission_callback' => [ RestKernel::class, 'can_execute' ],
            'callback'            => [ $this, 'handle' ],
        ]);
    }

    public function handle(WP_REST_Request $request): WP_REST_Response {

        $post_id = Input::int($request->get_param('post_id'), 0, 0, PHP_INT_MAX);
        if ($post_id <= 0) {
            return new WP_REST_Response(['ok' => false, 'error' => 'invalid_post_id'], 400);
        }

        $force = Input::bool($request->get_param('force'), false);

        // Read stored state (meta + cached summary).
        $score   = (int) get_post_meta($post_id, '_seojusai_score', true);
        $stored  = PageAuditSummary::load($post_id);

        $has_summary = is_array($stored) && !empty($stored['ok']);
        $has_score   = metadata_exists('post', $post_id, '_seojusai_score');

        // Якщо користувач явно ініціював перевірку — ставимо задачу у чергу, без синхронного аналізу.
        if ($force) {
            $queue = new TaskQueue();
            $payload = [
                'post_id' => $post_id,
                'reason'  => 'ui_force_refresh',
            ];

            // Уникати дублювання: ключ містить модифікацію поста.
            $modified = (int) get_post_modified_time('U', true, $post_id);
            $key = sprintf('page_audit_%d_%d', $post_id, $modified > 0 ? $modified : time());
            $queue->enqueue('page_audit', $payload, $key);

            return new WP_REST_Response([
                'ok'         => true,
                'enqueued'   => true,
                'post_id'    => $post_id,
                'score'      => $has_score ? $score : 0,
                'updated_at' => (int) get_post_meta($post_id, '_seojusai_score_updated', true),
                'counts'     => [ 'critical' => 0, 'warning' => 0, 'info' => 0, 'total' => 0 ],
                'issues'     => [],
            ], 202);
        }

        // Якщо ще немає реальних даних — запускаємо асинхронний аудит через чергу.
        if (!$has_summary && !$has_score) {
            $queue = new TaskQueue();

            $payload = [
                'post_id' => $post_id,
                'reason'  => 'ui_summary_request',
            ];

            $key = sprintf('page_audit_%d', $post_id);
            $queue->enqueue('page_audit', $payload, $key);

            return new WP_REST_Response([
                'ok'         => true,
                'enqueued'   => true,
                'post_id'    => $post_id,
                'score'      => 0,
                'updated_at' => 0,
                'counts'     => [ 'critical' => 0, 'warning' => 0, 'info' => 0, 'total' => 0 ],
                'issues'     => [],
            ], 202);
        }

        $summary = PageAuditSummary::compute($post_id, false);

        // Зберігаємо лише "готовий" результат, а не проміжний стан.
        if (!empty($summary['ok']) && empty($summary['enqueued'])) {
            PageAuditSummary::store($post_id, $summary);
        }

        return new WP_REST_Response($summary, 200);
    }
}
