<?php
declare(strict_types=1);

namespace SEOJusAI\Rest\Controllers;

use WP_REST_Request;
use WP_REST_Response;
use SEOJusAI\Rest\RestKernel;
use SEOJusAI\Rest\Contracts\RestControllerInterface;
use SEOJusAI\Input\Input;
use SEOJusAI\Learning\LearningEventRepository;
use SEOJusAI\Learning\LearningService;

defined('ABSPATH') || exit;

final class LearningController implements RestControllerInterface {

    public function register_routes(): void {

        register_rest_route('seojusai/v1', '/learning/status', [
            'methods'             => 'GET',
            'permission_callback' => [RestKernel::class, 'can_manage'],
            'callback'            => [$this, 'status'],
        ]);

        register_rest_route('seojusai/v1', '/learning/events', [
            'methods'             => 'GET',
            'permission_callback' => [RestKernel::class, 'can_manage'],
            'callback'            => [$this, 'events'],
            'args' => [
                'limit' => ['type'=>'integer','required'=>false],
                'status'=> ['type'=>'string','required'=>false],
            ],
        ]);
    }

    public function status(WP_REST_Request $req): WP_REST_Response {
        return new WP_REST_Response([
            'ok' => true,
            'enabled' => LearningService::enabled(),
            'observe_days' => LearningService::observe_days(),
        ], 200);
    }

    public function events(WP_REST_Request $req): WP_REST_Response {
        $limit = Input::int($req->get_param('limit'), 1, 200);
        $status = Input::key($req->get_param('status'));

        $repo = new LearningEventRepository();
        $rows = $repo->list_recent($limit, $status);

        return new WP_REST_Response(['ok'=>true,'items'=>$rows], 200);
    }
}
