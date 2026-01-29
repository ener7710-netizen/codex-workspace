<?php
declare(strict_types=1);

namespace SEOJusAI\Rest\Controllers;

use WP_REST_Request;
use WP_REST_Response;
use SEOJusAI\Input\Input;
use SEOJusAI\Rest\RestKernel;
use SEOJusAI\Rest\AbstractRestController;
use SEOJusAI\Rest\Contracts\RestControllerInterface;

defined('ABSPATH') || exit;

final class ReviewController extends AbstractRestController implements RestControllerInterface {

	public function register_routes(): void {
		register_rest_route('seojusai/v1', '/feedback', [
			'methods'             => 'POST',
			'permission_callback' => [ RestKernel::class, 'can_manage' ],
			'callback'            => [ $this, 'handle_feedback' ],
		]);
	}

	public function handle_feedback(WP_REST_Request $request): WP_REST_Response {
		$__raw = (string) $request->get_body();
		$__parsed = Input::json_array_strict($__raw, 80000);
		if (!$__parsed['ok']) return $this->error('Invalid JSON payload', (string)$__parsed['error'], $__parsed['error']==='payload_too_large' ? 413 : 400);
		$params = (array) $__parsed['data'];

		do_action('seojusai/kbe/store', [
			'decision_id' => sanitize_text_field($params['decision_id'] ?? ''),
			'feedback'    => sanitize_text_field($params['feedback'] ?? ''),
			'post_id'     => (int) ($params['post_id'] ?? 0)
		]);

		return $this->ok(['received' => true]);
	}
}
