<?php
declare(strict_types=1);

namespace SEOJusAI\Rest\Controllers;

use WP_REST_Request;
use WP_REST_Response;
use SEOJusAI\Input\Input;
use SEOJusAI\Budget\Budget;
use SEOJusAI\Core\EmergencyStop;
use SEOJusAI\Rest\RestKernel;
use SEOJusAI\Rest\AbstractRestController;
use SEOJusAI\Rest\Contracts\RestControllerInterface;

defined('ABSPATH') || exit;

final class BudgetController extends AbstractRestController implements RestControllerInterface {

	private Budget $budget;

	public function __construct() {
		parent::__construct();
		$this->budget = new Budget();
	}

	public function register_routes(): void {
		register_rest_route('seojusai/v1', '/budget', [
			'methods'             => 'GET',
			'permission_callback' => [ RestKernel::class, 'can_manage' ],
			'callback'            => [ $this, 'get_budget' ],
		]);

		register_rest_route('seojusai/v1', '/budget', [
			'methods'             => 'POST',
			'permission_callback' => [ RestKernel::class, 'can_execute' ],
			'callback'            => [ $this, 'update_budget' ],
		]);
	}

	public function get_budget(): WP_REST_Response {
		return $this->ok([
			'daily_limit'   => $this->budget->get_daily_limit(),
			'daily_used'    => $this->budget->get_daily_used(),
			'monthly_limit' => $this->budget->get_monthly_limit(),
			'is_blocked'    => $this->budget->is_blocked() || EmergencyStop::is_active()
		]);
	}

	public function update_budget(WP_REST_Request $request): WP_REST_Response {
		if (EmergencyStop::is_active()) return $this->error('Emergency active', 'blocked', 423);

		$daily = $request->get_param('daily_limit');
		if ($daily !== null) $this->budget->set_daily_limit(Input::int($daily, $this->budget->get_daily_limit(), 0, 1000000));

		return $this->ok(['success' => true]);
	}
}
