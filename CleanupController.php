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

final class CleanupController extends AbstractRestController implements RestControllerInterface {

	public function register_routes(): void {
		register_rest_route('seojusai/v1', '/cleanup', [
			'methods'             => 'POST',
			'permission_callback' => [ RestKernel::class, 'can_manage' ],
			'callback'            => [ $this, 'cleanup' ],
		]);
	}

	public function cleanup(WP_REST_Request $request): WP_REST_Response {
		if (EmergencyStop::is_active()) return $this->error('Emergency active', 'blocked', 423);

		global $wpdb;
		$days = Input::int($request->get_param('snapshots_keep_days'), 30, 1, 3650);
		$threshold = gmdate('Y-m-d H:i:s', time() - ($days * DAY_IN_SECONDS));

		$deleted = $wpdb->query($wpdb->prepare(
			"DELETE FROM {$wpdb->prefix}seojusai_snapshots WHERE created_at < %s",
			$threshold
		));

		return $this->ok(['deleted_rows' => $deleted]);
	}
}
