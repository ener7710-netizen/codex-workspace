<?php
declare(strict_types=1);

namespace SEOJusAI\Rest\Controllers;

use WP_REST_Request;
use WP_REST_Response;
use SEOJusAI\Input\Input;
use SEOJusAI\Core\EmergencyStop;
use SEOJusAI\Rest\AbstractRestController;
use SEOJusAI\Rest\Contracts\RestControllerInterface;
use SEOJusAI\Rest\RestKernel;
use SEOJusAI\Competitive\CompetitiveRepository;
use SEOJusAI\Competitive\MarketRules;
use SEOJusAI\Competitive\MarketRefresher;
use SEOJusAI\Tasks\TaskQueue;

defined('ABSPATH') || exit;

/**
 * MarketController
 *
 * REST API для "ринкових сигналів":
 * - керування списком конкурентів
 * - запуск сканування (через Action Scheduler якщо доступно)
 * - отримання зведення та ринкових правил
 */
final class MarketController extends AbstractRestController implements RestControllerInterface {

    private CompetitiveRepository $repo;

    public function __construct() {
        parent::__construct();
        $this->repo = new CompetitiveRepository();
    }

    public function register_routes(): void {
        register_rest_route('seojusai/v1', '/market/competitors', [
            'methods'             => 'GET',
            'permission_callback' => [ RestKernel::class, 'can_manage' ],
            'callback'            => [ $this, 'list_competitors' ],
        ]);

        // Canon 2026: competitors come only from SERP/Gemini, no manual input.

        register_rest_route('seojusai/v1', '/market/refresh', [
            'methods'             => 'POST',
            'permission_callback' => [ RestKernel::class, 'can_manage' ],
            'callback'            => [ $this, 'refresh' ],
        ]);

        register_rest_route('seojusai/v1', '/market/competitors/(?P<id>\d+)/ignore', [
            'methods'             => 'POST',
            'permission_callback' => [ RestKernel::class, 'can_manage' ],
            'callback'            => [ $this, 'ignore' ],
        ]);

        register_rest_route('seojusai/v1', '/market/summary', [
            'methods'             => 'GET',
            'permission_callback' => [ RestKernel::class, 'can_manage' ],
            'callback'            => [ $this, 'summary' ],
        ]);
    }

    public function list_competitors(WP_REST_Request $request): WP_REST_Response {
        return $this->ok([
            'items' => $this->repo->list_competitors(),
        ]);
    }

    public function refresh(WP_REST_Request $request): WP_REST_Response {
        $max_q = Input::int($request->get_param('max_queries'), 8, 1, 25);

        if (EmergencyStop::is_active()) {
            return new WP_REST_Response(['ok' => false, 'error' => 'Emergency Stop активний.'], 423);
        }

        $queue = new TaskQueue();
        $key = 'market_refresh:' . md5((string) $max_q);
        $ok = $queue->enqueue('market_refresh', [
            'post_id' => 0,
            'max_queries' => $max_q,
            'priority' => 'medium',
        ], $key);

        return $this->ok(['queued' => (bool) $ok, 'max_queries' => $max_q]);
    }

    public function ignore(WP_REST_Request $request): WP_REST_Response {
        $id = Input::int($request->get_param('id'), 0, 0, PHP_INT_MAX);
        $ignored = Input::bool($request->get_param('ignored'), false);
        $this->repo->set_ignored($id, $ignored);
        return $this->ok(['ok' => true]);
    }

    public function summary(WP_REST_Request $request): WP_REST_Response {
        $sum = $this->repo->summary();
        $rules = MarketRules::get();
        return $this->ok([
            'summary' => $sum,
            'rules' => $rules,
        ]);
    }
}
