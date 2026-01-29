<?php
declare(strict_types=1);

namespace SEOJusAI\Rest\Controllers;

use SEOJusAI\Rest\RestKernel;
use SEOJusAI\Rest\Contracts\RestControllerInterface;
use SEOJusAI\Experiments\ExperimentsRepository;

defined('ABSPATH') || exit;

final class ExperimentsController implements RestControllerInterface {

	private const NS = 'seojusai/v1';

	public function register_routes(): void {
		register_rest_route(self::NS, '/experiments', [
			'methods' => 'GET',
			'callback' => [$this, 'list'],
			'permission_callback' => [self::class, 'can_manage'],
		]);

		register_rest_route(self::NS, '/experiments/upsert', [
			'methods' => 'POST',
			'callback' => [$this, 'upsert'],
			'permission_callback' => [self::class, 'can_manage'],
		]);

		register_rest_route(self::NS, '/experiments/status', [
			'methods' => 'POST',
			'callback' => [$this, 'status'],
			'permission_callback' => [self::class, 'can_manage'],
		]);
	}

	public static function can_manage(\WP_REST_Request $req): bool {
		return RestKernel::can_execute($req);
	}

	public function list(\WP_REST_Request $req) {
		$repo = new ExperimentsRepository();
		return rest_ensure_response(['experiments' => $repo->all()]);
	}

	public function upsert(\WP_REST_Request $req) {
		$body = $req->get_json_params();
		if (!is_array($body)) {
			return new \WP_Error('seojusai_bad_request', __('Невірний JSON', 'seojusai'), ['status' => 400]);
		}
		$exp = [
			'id' => isset($body['id']) ? (int)$body['id'] : 0,
			'name' => sanitize_text_field((string)($body['name'] ?? '')),
			'status' => sanitize_key((string)($body['status'] ?? 'running')),
			'type' => sanitize_key((string)($body['type'] ?? 'cta_text')),
			'selector' => sanitize_text_field((string)($body['selector'] ?? '')),
			'variant_a' => sanitize_text_field((string)($body['variant_a'] ?? '')),
			'variant_b' => sanitize_text_field((string)($body['variant_b'] ?? '')),
			'split' => max(1, min(99, (int)($body['split'] ?? 50))),
		];
		if ($exp['name'] === '' || $exp['selector'] === '') {
			return new \WP_Error('seojusai_validation', __('Потрібні поля: name, selector', 'seojusai'), ['status' => 422]);
		}
		$repo = new ExperimentsRepository();
		$exp = $repo->upsert($exp);
		return rest_ensure_response(['experiment' => $exp]);
	}

	public function status(\WP_REST_Request $req) {
		$body = $req->get_json_params();
		if (!is_array($body)) {
			return new \WP_Error('seojusai_bad_request', __('Невірний JSON', 'seojusai'), ['status' => 400]);
		}
		$id = (int)($body['id'] ?? 0);
		$status = sanitize_key((string)($body['status'] ?? 'paused'));
		if ($id <= 0 || !in_array($status, ['running','paused','completed'], true)) {
			return new \WP_Error('seojusai_validation', __('Невірні id/status', 'seojusai'), ['status' => 422]);
		}
		$repo = new ExperimentsRepository();
		$repo->set_status($id, $status);
		return rest_ensure_response(['ok' => true]);
	}
}
