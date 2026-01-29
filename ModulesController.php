<?php
declare(strict_types=1);

namespace SEOJusAI\Rest\Controllers;

use WP_REST_Request;
use WP_REST_Response;
use SEOJusAI\Input\Input;
use SEOJusAI\Core\ModuleRegistry;
use SEOJusAI\Core\EmergencyStop;
use SEOJusAI\Rest\RestKernel;
use SEOJusAI\Rest\AbstractRestController;
use SEOJusAI\Rest\Contracts\RestControllerInterface;

defined('ABSPATH') || exit;

final class ModulesController extends AbstractRestController implements RestControllerInterface {

	private ModuleRegistry $modules;

	public function __construct() {
		parent::__construct();
		$this->modules = ModuleRegistry::instance();
	}

	public function register_routes(): void {
		register_rest_route('seojusai/v1', '/modules', [
			'methods'             => 'GET',
			'permission_callback' => [ RestKernel::class, 'can_manage' ],
			'callback'            => [ $this, 'get_modules' ],
		]);

		register_rest_route('seojusai/v1', '/modules/toggle', [
			'methods'             => 'POST',
			'permission_callback' => [ RestKernel::class, 'can_execute' ],
			'callback'            => [ $this, 'toggle_module' ],
		]);
	}

	public function get_modules(): WP_REST_Response {
		return $this->ok(['modules' => $this->modules->all()]);
	}

	public function toggle_module(WP_REST_Request $request): WP_REST_Response {
		if (EmergencyStop::is_active()) return $this->error('Emergency active', 'blocked', 423);

		$slug = sanitize_key((string)$request->get_param('slug'));
		$enabled = (bool)$request->get_param('enabled');

		$this->modules->set_enabled($slug, $enabled);
		return $this->ok(['success' => true]);
	}
}
