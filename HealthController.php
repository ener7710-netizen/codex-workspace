<?php
declare(strict_types=1);

namespace SEOJusAI\Rest\Controllers;

use WP_REST_Response;
use SEOJusAI\Core\HealthCheck;
use SEOJusAI\Core\EmergencyStop;
use SEOJusAI\Rest\RestKernel;
use SEOJusAI\Rest\AbstractRestController;
use SEOJusAI\Rest\Contracts\RestControllerInterface;

defined('ABSPATH') || exit;

final class HealthController extends AbstractRestController implements RestControllerInterface {

	public function register_routes(): void {
		register_rest_route('seojusai/v1', '/health', [
			'methods'             => 'GET',
			'permission_callback' => [ RestKernel::class, 'can_manage' ],
			'callback'            => [ $this, 'get_health' ],
		]);
	}

	public function get_health(): WP_REST_Response {
		$health = new HealthCheck($this->plugin);

		return new WP_REST_Response([
			'health'    => $health->snapshot(),
			'degraded'  => $this->plugin->is_degraded_mode(),
			'emergency' => EmergencyStop::is_active(),
			'timestamp' => time(),
		], 200);
	}
}
