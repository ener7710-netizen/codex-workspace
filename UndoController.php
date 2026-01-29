<?php
declare(strict_types=1);

namespace SEOJusAI\Rest\Controllers;

use WP_REST_Request;
use WP_REST_Response;
use SEOJusAI\Input\Input;
use SEOJusAI\Core\EmergencyStop;
use SEOJusAI\Rest\RestKernel;
use SEOJusAI\Snapshots\SnapshotService;
use SEOJusAI\Rest\AbstractRestController;
use SEOJusAI\Rest\Contracts\RestControllerInterface;

defined('ABSPATH') || exit;

final class UndoController extends AbstractRestController implements RestControllerInterface {

	private SnapshotService $snapshots;

	public function __construct() {
		parent::__construct();
		$this->snapshots = new SnapshotService();
	}

	public function register_routes(): void {
		register_rest_route('seojusai/v1', '/undo', [
			'methods'             => 'GET',
			'permission_callback' => [ RestKernel::class, 'can_manage' ],
			'callback'            => [ $this, 'list_snapshots' ],
		]);

		register_rest_route('seojusai/v1', '/undo', [
			'methods'             => 'POST',
			'permission_callback' => [ RestKernel::class, 'can_execute' ],
			'callback'            => [ $this, 'restore' ],
		]);
	}

	public function list_snapshots(WP_REST_Request $request): WP_REST_Response {
		$post_id = Input::int($request->get_param('post_id'), 0, 0, PHP_INT_MAX);
		return $this->ok([
			'snapshots' => $this->snapshots->repo()->list('post', $post_id, 10)
		]);
	}

	public function restore(WP_REST_Request $request): WP_REST_Response {
		if (EmergencyStop::is_active()) return $this->error('Emergency active', 'blocked', 423);

		$snapshot_id = Input::int($request->get_param('snapshot_id'), 0, 0, PHP_INT_MAX);
		$result = $this->snapshots->restore_post_snapshot($snapshot_id);

		return is_wp_error($result)
			? $this->error($result->get_error_message(), 'restore_failed', 400)
			: $this->ok(['success' => true]);
	}
}
