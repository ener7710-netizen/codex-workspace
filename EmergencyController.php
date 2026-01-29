<?php
declare(strict_types=1);

namespace SEOJusAI\Rest\Controllers;

use WP_REST_Request;
use WP_REST_Response;
use SEOJusAI\Input\Input;
use SEOJusAI\Core\EmergencyStop;
use SEOJusAI\Rest\RestKernel;
use SEOJusAI\Rest\AbstractRestController;
use SEOJusAI\Rest\Contracts\RestControllerInterface;

defined('ABSPATH') || exit;

final class EmergencyController extends AbstractRestController implements RestControllerInterface {

	public function register_routes(): void {
		register_rest_route('seojusai/v1', '/emergency', [
			'methods'             => 'GET',
			'permission_callback' => [ RestKernel::class, 'can_manage' ],
			'callback'            => [ $this, 'get_status' ],
		]);

		register_rest_route('seojusai/v1', '/emergency', [
			'methods'             => 'POST',
			'permission_callback' => [ RestKernel::class, 'can_manage' ],
			'callback'            => [ $this, 'set_status' ],
			'args'                => [
				'active' => [
					'type'     => 'boolean',
					'required' => true,
				],
			],
		]);
	}

	public function get_status(): WP_REST_Response {
		return new WP_REST_Response(['active' => EmergencyStop::is_active()], 200);
	}

	public function set_status(WP_REST_Request $request): WP_REST_Response {
		$active = Input::bool($request->get_param('active'), false);
		EmergencyStop::set($active);

		return new WP_REST_Response([
			'success' => true,
			'active'  => EmergencyStop::is_active(),
		], 200);
	}
}
