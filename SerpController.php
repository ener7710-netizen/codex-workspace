<?php
declare(strict_types=1);

namespace SEOJusAI\Rest\Controllers;

use WP_REST_Request;
use WP_REST_Response;
use SEOJusAI\Input\Input;
use SEOJusAI\SERP\SerpClient;
use SEOJusAI\Rest\RestKernel;
use SEOJusAI\Rest\AbstractRestController;
use SEOJusAI\Rest\Contracts\RestControllerInterface;

defined('ABSPATH') || exit;

final class SerpController extends AbstractRestController implements RestControllerInterface {

	private SerpClient $serp;

	public function __construct() {
		parent::__construct();
		$this->serp = new SerpClient();
	}

	public function register_routes(): void {
		register_rest_route('seojusai/v1', '/serp', [
			'methods'             => 'POST',
			'permission_callback' => [ RestKernel::class, 'can_manage' ],
			'callback'            => [ $this, 'search' ],
		]);
	}

	public function search(WP_REST_Request $request): WP_REST_Response {
		$query = Input::string($request->get_param('query'), 256, true);
		if (!$query) return $this->error('Query required', 'empty_query', 400);

		$items = $this->serp->search($query, 10);
		$processed = [];

		foreach ($items as $index => $item) {
			$processed[] = [
				'position' => $index + 1,
				'title'    => $item['title'] ?? '',
				'url'      => $item['url'] ?? '',
				'snippet'  => $item['snippet'] ?? '',
				'is_legal' => str_contains(strtolower($item['url']), 'law') || str_contains(strtolower($item['url']), 'advokat'),
			];
		}

		return $this->ok(['items' => $processed]);
	}
}
