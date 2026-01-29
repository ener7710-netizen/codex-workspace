<?php
declare(strict_types=1);

namespace SEOJusAI\Rest\Controllers;

use WP_REST_Request;
use WP_REST_Response;
use SEOJusAI\Input\Input;
use SEOJusAI\Autopilot\AutopilotEngine;
use SEOJusAI\Core\EmergencyStop;
use SEOJusAI\Rest\RestKernel;
use SEOJusAI\Rest\AbstractRestController;
use SEOJusAI\Rest\Contracts\RestControllerInterface;

defined('ABSPATH') || exit;

final class AutopilotController extends AbstractRestController implements RestControllerInterface {

	private AutopilotEngine $engine;

	public function __construct() {
		parent::__construct();
		$this->engine = new AutopilotEngine();
	}

	public function register_routes(): void {
		register_rest_route('seojusai/v1', '/autopilot', [
			'methods'             => 'GET',
			'permission_callback' => [ RestKernel::class, 'can_manage' ],
			'callback'            => [ $this, 'get_status' ],
		]);

		register_rest_route('seojusai/v1', '/autopilot', [
			'methods'             => 'POST',
			'permission_callback' => [ RestKernel::class, 'can_manage' ],
			'callback'            => [ $this, 'update_status' ],
		]);
	}

	public function get_status(): WP_REST_Response {
		return $this->ok([
			'mode'        => $this->engine->get_mode(),
			'allow_apply' => $this->engine->is_apply_allowed(),
			'full_safe'  => $this->engine->is_full_safe_enabled()
		]);
	}

	public function update_status(WP_REST_Request $request): WP_REST_Response {
		if (EmergencyStop::is_active()) return $this->error('Emergency active', 'blocked', 423);

		$mode = sanitize_key((string)$request->get_param('mode'));
		if (in_array($mode, ['shadow', 'limited', 'full'], true)) {
			$this->engine->set_mode($mode);
		}

		$allow_apply = $request->get_param('allow_apply');
		if ($allow_apply !== null) {
			$this->engine->set_allow_apply((bool)$allow_apply);
		}

		return $this->ok(['success' => true, 'mode' => $this->engine->get_mode()]);
	}
}
