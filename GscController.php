<?php
declare(strict_types=1);

namespace SEOJusAI\Rest\Controllers;

use WP_REST_Request;
use WP_REST_Response;
use SEOJusAI\Input\Input;
use SEOJusAI\GSC\GSCClient;
use SEOJusAI\Rest\RestKernel;
use SEOJusAI\Rest\AbstractRestController;
use SEOJusAI\Rest\Contracts\RestControllerInterface;

defined('ABSPATH') || exit;

final class GscController extends AbstractRestController implements RestControllerInterface {

	private GSCClient $gsc;

	public function __construct() {
		parent::__construct();
		$this->gsc = new GSCClient();
	}

	public function register_routes(): void {
		register_rest_route('seojusai/v1', '/gsc/properties', [
			'methods'             => 'GET',
			'permission_callback' => [ RestKernel::class, 'can_manage' ],
			'callback'            => [ $this, 'properties' ],
		]);

		register_rest_route('seojusai/v1', '/gsc/analytics', [
			// RestKernel enforces read-only for /seojusai/v1, so expose analytics as GET.
			'methods'             => 'GET',
			'permission_callback' => [ RestKernel::class, 'can_manage' ],
			'callback'            => [ $this, 'analytics' ],
		]);

		register_rest_route('seojusai/v1', '/gsc/timeseries', [
			'methods'             => 'GET',
			'permission_callback' => [ RestKernel::class, 'can_manage' ],
			'callback'            => [ $this, 'timeseries' ],
		]);
	}

	public function properties(): WP_REST_Response {
		return new WP_REST_Response([
			'connected'  => $this->gsc->is_connected(),
			'properties' => $this->gsc->list_properties(),
		], 200);
	}

	public function analytics(WP_REST_Request $request): WP_REST_Response {
		$site = sanitize_text_field((string) $request->get_param('site'));
		if (empty($site)) {
			return $this->error(__('Потрібен site property (параметр site).', 'seojusai'), 'missing_param', 400);
		}

		$days = (int) $request->get_param('days');
		if ($days <= 0) {
			$days = 28;
		}
		$days = max(1, min(365, $days));

		$data = $this->gsc->get_search_analytics($site, [
			'startDate' => gmdate('Y-m-d', strtotime('-' . $days . ' days')),
			'endDate'   => gmdate('Y-m-d'),
			'dimensions' => ['query', 'page'],
			'rowLimit'  => 500,
		]);
		return $this->ok(['rows' => $data]);
	}

	/**
	 * Щоденна динаміка GSC для графіків.
	 * dimensions: ['date']
	 */
	public function timeseries(WP_REST_Request $request): WP_REST_Response {
		$site = sanitize_text_field((string) $request->get_param('site'));
		if (empty($site)) {
			return $this->error(__('Потрібен site property (параметр site).', 'seojusai'), 'missing_param', 400);
		}

		$days = (int) $request->get_param('days');
		if ($days <= 0) {
			$days = 28;
		}
		$days = max(1, min(365, $days));

		$rows = $this->gsc->get_search_analytics($site, [
			'startDate'  => gmdate('Y-m-d', strtotime('-' . $days . ' days')),
			'endDate'    => gmdate('Y-m-d'),
			'dimensions' => ['date'],
			'rowLimit'   => 500,
		]);

		$out = [];
		foreach ($rows as $r) {
			$keys = isset($r['keys']) && is_array($r['keys']) ? $r['keys'] : [];
			$date = isset($keys[0]) ? (string) $keys[0] : '';
			if ($date === '') {
				continue;
			}
			$out[] = [
				'date'        => $date,
				'clicks'      => (int) round((float) ($r['clicks'] ?? 0)),
				'impressions' => (int) round((float) ($r['impressions'] ?? 0)),
				'ctr'         => (float) ($r['ctr'] ?? 0),
				'position'    => (float) ($r['position'] ?? 0),
			];
		}

		usort($out, static fn($a, $b) => strcmp((string) $a['date'], (string) $b['date']));
		return $this->ok(['timeseries' => $out]);
	}
}
