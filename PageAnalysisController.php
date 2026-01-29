<?php
declare(strict_types=1);

namespace SEOJusAI\Rest\Controllers;

use WP_REST_Request;
use WP_REST_Response;
use SEOJusAI\Analyze\PageFactsProvider;
use SEOJusAI\Analyze\SchemaFactsProvider;
use SEOJusAI\Analyze\PageTypeResolver;
use SEOJusAI\Crawl\HtmlSnapshot;
use SEOJusAI\Input\Input;
use SEOJusAI\Rest\AbstractRestController;
use SEOJusAI\Rest\Contracts\RestControllerInterface;
use SEOJusAI\Rest\RestKernel;

defined('ABSPATH') || exit;

/**
 * PageAnalysisController
 *
 * Read-only інспектор аналізу сторінки (для продакшн‑перевірки).
 * Джерело реальності: контент WP (Gutenberg) + HTML snapshot (за наявності).
 */
final class PageAnalysisController extends AbstractRestController implements RestControllerInterface {

    public function register_routes(): void {
        register_rest_route('seojusai/v1', '/page-analysis', [
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

        $post = get_post($post_id);
        if (!$post) {
            return new WP_REST_Response(['ok' => false, 'error' => 'post_not_found'], 404);
        }

        // 1) Контент/структура — як WordPress реально віддає блоки
        $facts = PageFactsProvider::get_by_url((string) get_permalink($post_id));

        // 2) HTML snapshot (якщо є, це ближче до фронта)
        $html = '';
        if (class_exists(HtmlSnapshot::class)) {
            $snapshot = HtmlSnapshot::load_for_post($post_id);
            if ($snapshot) {
                $html = (string) $snapshot->get_html();
            }
        }

        $page_type = 'unknown';
        if (class_exists(PageTypeResolver::class)) {
            try {
                $page_type = (string) (new PageTypeResolver())->resolve($post_id);
            } catch (\Throwable $e) {
                $page_type = 'unknown';
            }
        }

        // 3) Schema facts (JSON-LD) — з snapshot якщо є, інакше з контенту
        $schema = [];
        try {
            if ($html === '' && isset($facts['content_html'])) {
                $html = (string) $facts['content_html'];
            }
            if ($html !== '' && class_exists(SchemaFactsProvider::class)) {
                $schema = (new SchemaFactsProvider())->build($post_id, $html, $page_type);
            }
        } catch (\Throwable $e) {
            $schema = ['error' => $e->getMessage()];
        }

        return new WP_REST_Response([
            'ok'       => true,
            'post_id'  => $post_id,
            'url'      => (string) get_permalink($post_id),
            'page_type'=> $page_type,
            'facts'    => $facts,
            'schema'   => $schema,
        ], 200);
    }
}
