<?php
declare(strict_types=1);

namespace SEOJusAI\Rest\Controllers;

use WP_REST_Request;
use WP_REST_Response;
use SEOJusAI\Core\EmergencyStop;
use SEOJusAI\Input\Input;
use SEOJusAI\Rest\AbstractRestController;
use SEOJusAI\Rest\Contracts\RestControllerInterface;
use SEOJusAI\Rest\RestKernel;
use SEOJusAI\Tasks\TaskQueue;
use SEOJusAI\Capabilities\CapabilityGuard;
use SEOJusAI\Capabilities\CapabilityMap;

defined('ABSPATH') || exit;

/**
 * LinkingController
 *
 * Мінімальний міст: користувацька дія → асинхронна постановка задачі internal_link.
 */
final class LinkingController extends AbstractRestController implements RestControllerInterface {

	public function register_routes(): void {
		register_rest_route('seojusai/v1', '/linking/scan', [
			'methods'             => 'POST',
			'permission_callback' => [ RestKernel::class, 'can_execute' ],
			'callback'            => [ $this, 'scan' ],
		]);
	}

	public function scan(WP_REST_Request $request): WP_REST_Response {

		if (EmergencyStop::is_active()) {
			return new WP_REST_Response(['ok' => false, 'error' => 'Emergency Stop активний.'], 423);
		}

		if (!CapabilityGuard::can(CapabilityMap::RUN_ANALYSIS)) {
			return new WP_REST_Response(['ok' => false, 'error' => 'Forbidden'], 403);
		}

		$post_id = Input::int($request->get_param('post_id'), 0, 0, PHP_INT_MAX);
		$limit   = Input::int($request->get_param('limit'), 50, 1, 500);

		$queue = new TaskQueue();
		$enqueued = 0;

		if ($post_id > 0) {
			if (!current_user_can('edit_post', $post_id)) {
				return new WP_REST_Response(['ok' => false, 'error' => 'Forbidden'], 403);
			}

			$key = 'linking:' . $post_id;
			if ($queue->enqueue('internal_link', ['post_id' => $post_id, 'priority' => 'medium'], $key)) {
				$enqueued = 1;
			}

			return $this->ok(['queued' => $enqueued, 'scope' => 'single', 'post_id' => $post_id]);
		}

		$q = new \WP_Query([
			'post_type'      => ['page', 'post'],
			'post_status'    => ['publish', 'draft'],
			'posts_per_page' => $limit,
			'fields'         => 'ids',
			'orderby'        => 'modified',
			'order'          => 'DESC',
			'no_found_rows'  => true,
		]);

		foreach ((array) $q->posts as $pid) {
			$pid = (int) $pid;
			if ($pid <= 0) continue;
			if (!current_user_can('edit_post', $pid)) continue;

			$key = 'linking:' . $pid;
			if ($queue->enqueue('internal_link', ['post_id' => $pid, 'priority' => 'low'], $key)) {
				$enqueued++;
			}
		}

		return $this->ok(['queued' => $enqueued, 'scope' => 'batch', 'limit' => $limit]);
	}
}
