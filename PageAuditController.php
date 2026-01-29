<?php
declare(strict_types=1);

namespace SEOJusAI\Rest\Controllers;

use WP_REST_Request;
use WP_REST_Response;
use SEOJusAI\Input\Input;
use SEOJusAI\Rest\AbstractRestController;
use SEOJusAI\Rest\Contracts\RestControllerInterface;
use SEOJusAI\Rest\RestKernel;
use SEOJusAI\AI\Engine;
use SEOJusAI\Analyze\SchemaFactsProvider;
use SEOJusAI\Analyze\PageTypeResolver;
use SEOJusAI\Crawl\HtmlSnapshot;

defined('ABSPATH') || exit;

final class PageAuditController extends AbstractRestController implements RestControllerInterface {

	public function register_routes(): void {
		register_rest_route('seojusai/v1', '/page-audit', [
			'methods'             => 'POST',
			'permission_callback' => [ RestKernel::class, 'can_manage' ],
			'callback'            => [ $this, 'handle' ],
		]);
	}

	public function handle(WP_REST_Request $request): WP_REST_Response {

		$post_id = Input::int($request->get_param('post_id'), 0, 0, PHP_INT_MAX);

		if ($post_id <= 0) {
			return new WP_REST_Response([
				'ok'    => false,
				'error' => 'Некоректний ідентифікатор запису',
			], 400);
		}

		try {

			$context = [
				'post_id' => $post_id,
				'type'    => 'page',
			];

			$result = Engine::analyze_with_ai($context, 'page');

			if (!is_array($result)) {
				return new WP_REST_Response([
					'ok'    => false,
					'error' => 'Аналіз не повернув результат',
				], 500);
			}

		} catch (\Throwable $e) {
			return new WP_REST_Response([
				'ok'    => false,
				'error' => $e->getMessage(),
			], 500);
		}

		/* ================= Schema ================= */

		try {
			$page_type = class_exists(PageTypeResolver::class)
				? (new PageTypeResolver())->resolve($post_id)
				: 'unknown';

			$html = '';

			if (class_exists(HtmlSnapshot::class)) {
				// refresh snapshot to avoid editor-only blind spots (H1/schema/phone/address in theme/header/footer)
				$snapshot = HtmlSnapshot::refresh_for_post($post_id, false);
				if (!$snapshot) {
					$snapshot = HtmlSnapshot::load_for_post($post_id);
				}
				if ($snapshot) {
					$html = (string) $snapshot->get_html();
				}
			}

			if ($html !== '' && class_exists(SchemaFactsProvider::class)) {
				$result['schema'] = (new SchemaFactsProvider())
					->build($post_id, $html, (string) $page_type);
			}

		} catch (\Throwable $e) {
			$result['schema'] = ['error' => $e->getMessage()];
		}

		return new WP_REST_Response($result, 200);
	}
}
