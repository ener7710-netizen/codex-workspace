<?php
declare(strict_types=1);

namespace SEOJusAI\Rest\Controllers;

use WP_REST_Request;
use WP_REST_Response;
use SEOJusAI\Input\Input;
use SEOJusAI\Core\EmergencyStop;
use SEOJusAI\AI\PageVsSerpAnalyzer;
use SEOJusAI\AI\DecisionPipeline;
use SEOJusAI\Rest\RestKernel;
use SEOJusAI\Rest\AbstractRestController;
use SEOJusAI\Rest\Contracts\RestControllerInterface;

defined('ABSPATH') || exit;

final class PageCompareController extends AbstractRestController implements RestControllerInterface {

	private PageVsSerpAnalyzer $analyzer;
	private DecisionPipeline $pipeline;

	public function __construct() {
		parent::__construct();
		$this->analyzer = new PageVsSerpAnalyzer();
		$this->pipeline = new DecisionPipeline();
	}

	public function register_routes(): void {
		register_rest_route('seojusai/v1', '/page/compare', [
			'methods'             => 'POST',
			'permission_callback' => [ RestKernel::class, 'can_execute' ],
			'callback'            => [ $this, 'compare' ],
		]);
	}

	public function compare(WP_REST_Request $request): WP_REST_Response {
		if (EmergencyStop::is_active()) return $this->error('Emergency active', 'blocked', 423);

		$post_id = Input::int($request->get_param('post_id'), 0, 0, PHP_INT_MAX);
		$query   = Input::string($request->get_param('query'), 256, true);

		if ($post_id <= 0 || empty($query)) return $this->error('Bad request', 'invalid_params', 400);

		$analysis = $this->analyzer->analyze($post_id, $query);
		$decision = $this->pipeline->run($analysis);

		return $this->ok([
			'analysis' => $analysis,
			'decision' => $decision
		]);
	}
}
