<?php
declare(strict_types=1);

namespace SEOJusAI\Rest\Controllers;

use WP_REST_Request;
use WP_REST_Response;
use SEOJusAI\Rest\RestKernel;
use SEOJusAI\Rest\Contracts\RestControllerInterface;
use SEOJusAI\Input\Input;
use SEOJusAI\AIRiskFunnel\AIRiskFunnelService;

defined('ABSPATH') || exit;

final class AIRiskFunnelController implements RestControllerInterface {

    public function register_routes(): void {

        register_rest_route('seojusai/v1', '/risk-funnel/analyze', [
            'methods'             => 'POST',
            'permission_callback' => [RestKernel::class, 'can_manage'],
            'callback'            => [$this, 'analyze'],
            'args' => [
                'post_id' => ['type' => 'integer', 'required' => true],
            ],
        ]);
    }

    public function analyze(WP_REST_Request $req): WP_REST_Response {
        $post_id = Input::int($req->get_param('post_id'), 1, PHP_INT_MAX);
        $service = new AIRiskFunnelService();
        $data = $service->analyze_post($post_id);

        return new WP_REST_Response($data, 200);
    }
}
