<?php
declare(strict_types=1);

namespace SEOJusAI\Rest\Controllers;

use WP_REST_Request;
use WP_REST_Response;
use SEOJusAI\Input\Input;
use SEOJusAI\Core\EmergencyStop;
use SEOJusAI\Explain\ExplainRepository;
use SEOJusAI\Rest\RestKernel;
use SEOJusAI\Rest\AbstractRestController;
use SEOJusAI\Rest\Contracts\RestControllerInterface;

defined('ABSPATH') || exit;

final class ExplainController extends AbstractRestController implements RestControllerInterface {

	public function register_routes(): void {
		register_rest_route('seojusai/v1', '/explain', [
			'methods'             => 'GET',
			'permission_callback' => [ RestKernel::class, 'can_execute' ],
			'callback'            => [ $this, 'get_explain' ],
			'args'                => [
				'post_id' => [ 'required' => true, 'type' => 'integer' ]
			],
		]);
	}

	public function get_explain(WP_REST_Request $request): WP_REST_Response {
		if (EmergencyStop::is_active()) return $this->ok(['items' => [], 'blocked' => true]);

		$post_id = Input::int($request->get_param('post_id'), 0, 0, PHP_INT_MAX);
		$repo = new ExplainRepository();

		return $this->ok([
			'items' => $repo->list('post', $post_id, 20)
		]);
	}
}
