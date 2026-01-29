<?php
declare(strict_types=1);

namespace SEOJusAI\Rest\Controllers;

use WP_REST_Request;
use WP_REST_Response;
use SEOJusAI\AI\PageActions\PageActionPlanner;
use SEOJusAI\Rest\RestKernel;
use SEOJusAI\Rest\AbstractRestController;
use SEOJusAI\Rest\Contracts\RestControllerInterface;

defined('ABSPATH') || exit;

/**
 * PageActionsController
 *
 * Read-only endpoint: повертає запропоновані AI дії для конкретної сторінки.
 *
 * Інваріанти:
 * - не виконує змін
 * - не запускає Autopilot
 */
final class PageActionsController extends AbstractRestController implements RestControllerInterface {

    public function register_routes(): void {
        register_rest_route('seojusai/v1', '/analytics/page-actions', [
            'methods'             => 'GET',
            'permission_callback' => [ RestKernel::class, 'can_manage' ],
            'callback'            => [ $this, 'page_actions' ],
        ]);
    }

    public function page_actions(WP_REST_Request $request): WP_REST_Response {
        $url = (string) $request->get_param('url');
        $url = sanitize_text_field($url);

        if ($url === '') {
            return $this->ok([
                'ok' => false,
                'message' => __('Параметр url є обов\'язковим.', 'seojusai'),
            ]);
        }

        $top = (int) $request->get_param('top');
        if ($top <= 0) {
            $top = 50;
        }
        $top = max(5, min(200, $top));

        try {
            $planner = new PageActionPlanner();
            $data = $planner->plan($url, $top);
            return $this->ok($data);
        } catch (\Throwable $e) {
            return $this->ok([
                'ok' => false,
                'message' => __('Не вдалося сформувати AI дії для сторінки.', 'seojusai'),
            ]);
        }
    }
}
