<?php
declare(strict_types=1);

namespace SEOJusAI\Rest\Controllers;

use WP_REST_Request;
use WP_REST_Response;
use SEOJusAI\Analytics\ObjectiveDatasetService;
use SEOJusAI\Analytics\PagesMergeService;
use SEOJusAI\AI\Integrations\GeminiAnalyticsGateway;
use SEOJusAI\Rest\RestKernel;
use SEOJusAI\Rest\AbstractRestController;
use SEOJusAI\Rest\Contracts\RestControllerInterface;

defined('ABSPATH') || exit;

/**
 * AnalyticsController
 *
 * Надає зведений датасет (GSC + GA4) для UI та/або AI.
 * Використовує тільки снапшоти, щоб дані були об'єктивними.
 */
final class AnalyticsController extends AbstractRestController implements RestControllerInterface {

    public function register_routes(): void {
        register_rest_route('seojusai/v1', '/analytics/dataset', [
            'methods'             => 'GET',
            'permission_callback' => [ RestKernel::class, 'can_manage' ],
            'callback'            => [ $this, 'dataset' ],
        ]);

        register_rest_route('seojusai/v1', '/analytics/gemini', [
            'methods'             => 'GET',
            'permission_callback' => [ RestKernel::class, 'can_manage' ],
            'callback'            => [ $this, 'gemini' ],
        ]);

        register_rest_route('seojusai/v1', '/analytics/gemini/refresh', [
            'methods'             => 'POST',
            'permission_callback' => [ RestKernel::class, 'can_manage' ],
            'callback'            => [ $this, 'gemini_refresh' ],
        ]);

        register_rest_route('seojusai/v1', '/analytics/pages', [
            'methods'             => 'GET',
            'permission_callback' => [ RestKernel::class, 'can_manage' ],
            'callback'            => [ $this, 'pages' ],
        ]);
    }

    public function dataset(WP_REST_Request $request): WP_REST_Response {
        $top = (int) $request->get_param('top');
        if ($top <= 0) {
            $top = 100;
        }
        $top = max(5, min(500, $top));

        $svc = new ObjectiveDatasetService();
        $data = $svc->build($top);
        return $this->ok($data);
    }

    public function gemini(WP_REST_Request $request): WP_REST_Response {
        $top = (int) $request->get_param('top');
        if ($top <= 0) {
            $top = 30;
        }
        $top = max(5, min(100, $top));

        $data = GeminiAnalyticsGateway::get_or_compute($top, false);
        if (!is_array($data)) {
            return $this->ok([
                'ok' => false,
                'message' => __('Gemini аналітика недоступна. Перевір ключ Gemini та GA4/GSC.', 'seojusai'),
            ]);
        }

        return $this->ok([
            'ok' => true,
            'data' => $data,
        ]);
    }

    public function gemini_refresh(WP_REST_Request $request): WP_REST_Response {
        $top = (int) $request->get_param('top');
        if ($top <= 0) {
            $top = 30;
        }
        $top = max(5, min(100, $top));

        $data = GeminiAnalyticsGateway::get_or_compute($top, true);
        if (!is_array($data)) {
            return $this->ok([
                'ok' => false,
                'message' => __('Не вдалося оновити Gemini аналітику.', 'seojusai'),
            ]);
        }

        return $this->ok([
            'ok' => true,
            'data' => $data,
        ]);
    }

    /**
     * Серверний merge GA4+GSC сторінок.
     * Параметри:
     * - days (int)
     * - limit (int)
     * - site (string) - GSC property
     * - breakdown: none|country|device|source
     */
    public function pages(WP_REST_Request $request): WP_REST_Response {
        $days = (int) $request->get_param('days');
        if ($days <= 0) { $days = 30; }
        $limit = (int) $request->get_param('limit');
        if ($limit <= 0) { $limit = 200; }
        $site = sanitize_text_field((string) $request->get_param('site'));
        $breakdown = sanitize_text_field((string) $request->get_param('breakdown'));
        if ($breakdown === '') { $breakdown = 'none'; }

        $data = PagesMergeService::get_merged([
            'days' => $days,
            'limit' => $limit,
            'site' => $site,
            'breakdown' => $breakdown,
        ]);

        return $this->ok($data);
    }
}
