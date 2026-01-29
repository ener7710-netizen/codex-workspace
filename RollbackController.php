<?php
declare(strict_types=1);

namespace SEOJusAI\Rest\Controllers;

use WP_REST_Request;
use WP_REST_Response;
use SEOJusAI\Input\Input;
use SEOJusAI\Rest\RestKernel;
use SEOJusAI\Snapshots\SnapshotService;
use SEOJusAI\Rest\Contracts\RestControllerInterface;

defined('ABSPATH') || exit;

final class RollbackController implements RestControllerInterface {

	public function register_routes(): void {
		register_rest_route('seojusai/v1', '/rollback', [
			'methods'             => 'POST',
			'permission_callback' => [RestKernel::class, 'can_execute'],
			'callback'            => [$this, 'rollback'],
			'args'                => [
				'snapshot_id' => [
					'type'     => 'integer',
					'required' => true,
				],
			],
		]);
	}

	public function rollback(WP_REST_Request $request): WP_REST_Response {
		$snapshot_id = (int) $request->get_param('snapshot_id');

		$service = new SnapshotService();
		$result  = $service->restore_post_snapshot($snapshot_id);

		if (is_wp_error($result)) {
			return new WP_REST_Response(['error' => $result->get_error_message()], 400);
		}

		return new WP_REST_Response(['success' => true], 200);
	}
}
