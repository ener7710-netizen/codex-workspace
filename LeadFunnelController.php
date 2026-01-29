<?php
declare(strict_types=1);

namespace SEOJusAI\Rest\Controllers;

use SEOJusAI\Rest\RestKernel;
use SEOJusAI\Rest\Contracts\RestControllerInterface;
use SEOJusAI\LeadFunnel\LeadFunnelService;
use SEOJusAI\Input\Input;

defined('ABSPATH') || exit;

final class LeadFunnelController implements RestControllerInterface {

	private const NS = 'seojusai/v1';

	public function register_routes(): void {
		register_rest_route(self::NS, '/lead-funnel/summary', [
			'methods' => 'GET',
			'callback' => [$this, 'summary'],
			'permission_callback' => [self::class, 'can_read'],
		]);

		register_rest_route(self::NS, '/lead-funnel/page', [
			'methods' => 'GET',
			'callback' => [$this, 'page'],
			'permission_callback' => [self::class, 'can_read'],
		]);
	}

	public static function can_read(\WP_REST_Request $req): bool {
		return RestKernel::can_execute($req);
	}

	public function summary(\WP_REST_Request $req) {
		$limit = (int) ($req->get_param('limit') ?? 20);
		$svc = new LeadFunnelService();
		return rest_ensure_response([
			'top' => $svc->top_pages_by_impact($limit),
		]);
	}

	public function page(\WP_REST_Request $req) {
		$post_id = (int) ($req->get_param('post_id') ?? 0);
		if ($post_id <= 0) {
			return new \WP_Error('seojusai_bad_request', __('Невірний post_id', 'seojusai'), ['status' => 400]);
		}
		$svc = new LeadFunnelService();
		return rest_ensure_response($svc->analyze_post($post_id));
	}
}
