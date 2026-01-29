<?php
declare(strict_types=1);

namespace SEOJusAI\Rest\Controllers;

use WP_REST_Request;
use WP_REST_Response;
use SEOJusAI\GA4\GA4Client;
use SEOJusAI\GA4\Ga4Snapshot;
use SEOJusAI\Rest\RestKernel;
use SEOJusAI\Rest\AbstractRestController;
use SEOJusAI\Rest\Contracts\RestControllerInterface;

defined('ABSPATH') || exit;

/**
 * Ga4Controller
 *
 * Read-only REST endpoints for GA4.
 * Note: RestKernel enforces read-only methods in /seojusai/v1, so we expose
 * GA4 endpoints as GET.
 */
final class Ga4Controller extends AbstractRestController implements RestControllerInterface {

    private GA4Client $ga4;

    public function __construct() {
        parent::__construct();
        $this->ga4 = new GA4Client();
    }

    public function register_routes(): void {

        register_rest_route('seojusai/v1', '/ga4/status', [
            'methods'             => 'GET',
            'permission_callback' => [ RestKernel::class, 'can_manage' ],
            'callback'            => [ $this, 'status' ],
        ]);

        register_rest_route('seojusai/v1', '/ga4/overview', [
            'methods'             => 'GET',
            'permission_callback' => [ RestKernel::class, 'can_manage' ],
            'callback'            => [ $this, 'overview' ],
        ]);

        register_rest_route('seojusai/v1', '/ga4/pages', [
            'methods'             => 'GET',
            'permission_callback' => [ RestKernel::class, 'can_manage' ],
            'callback'            => [ $this, 'pages' ],
        ]);

        register_rest_route('seojusai/v1', '/ga4/timeseries', [
            'methods'             => 'GET',
            'permission_callback' => [ RestKernel::class, 'can_manage' ],
            'callback'            => [ $this, 'timeseries' ],
        ]);
    }

    public function status(): WP_REST_Response {
        return $this->ok([
            'connected'   => $this->ga4->is_connected(),
            'keyPath'     => \SEOJusAI\GA4\Ga4ServiceAccount::get_key_path(),
            'propertyId'  => (string) get_option('seojusai_ga4_property_id', ''),
        ]);
    }

    public function overview(WP_REST_Request $request): WP_REST_Response {
        $days = (int) $request->get_param('days');
        if ($days <= 0) {
            $days = 30;
        }

        // Prefer latest snapshot to keep calls cheap/objective.
        $snap = Ga4Snapshot::latest();
        if (is_array($snap) && isset($snap['data']['overview']) && is_array($snap['data']['overview'])) {
            return $this->ok([ 'source' => 'snapshot', 'overview' => $snap['data']['overview'], 'snapshot' => $snap['_snapshot'] ?? [] ]);
        }

        if (!$this->ga4->is_connected()) {
            return $this->error(__('GA4 не підключено (перевір Property ID та ключ).', 'seojusai'), 'ga4_not_connected', 400);
        }

        $overview = $this->ga4->get_overview($days);
        if (!empty($overview)) {
            Ga4Snapshot::save([
                'days' => $days,
                'overview' => $overview,
            ]);
        }

        return $this->ok([ 'source' => 'live', 'overview' => $overview ]);
    }

    public function pages(WP_REST_Request $request): WP_REST_Response {
        $days = (int) $request->get_param('days');
        if ($days <= 0) {
            $days = 30;
        }
        $limit = (int) $request->get_param('limit');
        if ($limit <= 0) {
            $limit = 200;
        }

        $snap = Ga4Snapshot::latest();
        if (is_array($snap) && isset($snap['data']['pages']) && is_array($snap['data']['pages'])) {
            return $this->ok([ 'source' => 'snapshot', 'pages' => $snap['data']['pages'], 'snapshot' => $snap['_snapshot'] ?? [] ]);
        }

        if (!$this->ga4->is_connected()) {
            return $this->error(__('GA4 не підключено (перевір Property ID та ключ).', 'seojusai'), 'ga4_not_connected', 400);
        }

        $pages = $this->ga4->get_pages($days, $limit);
        if (!empty($pages)) {
            Ga4Snapshot::save([
                'days' => $days,
                'pages' => $pages,
            ]);
        }

        return $this->ok([ 'source' => 'live', 'pages' => $pages ]);
    }

    public function timeseries(WP_REST_Request $request): WP_REST_Response {
        $days = (int) $request->get_param('days');
        if ($days <= 0) {
            $days = 30;
        }

        $snap = Ga4Snapshot::latest();
        if (is_array($snap) && isset($snap['data']['timeseries']) && is_array($snap['data']['timeseries'])) {
            return $this->ok([ 'source' => 'snapshot', 'timeseries' => $snap['data']['timeseries'], 'snapshot' => $snap['_snapshot'] ?? [] ]);
        }

        if (!$this->ga4->is_connected()) {
            return $this->error(__('GA4 не підключено (перевір Property ID та ключ).', 'seojusai'), 'ga4_not_connected', 400);
        }

        $series = $this->ga4->get_timeseries($days);
        if (!empty($series)) {
            Ga4Snapshot::save([
                'days' => $days,
                'timeseries' => $series,
            ]);
        }

        return $this->ok([ 'source' => 'live', 'timeseries' => $series ]);
    }
}
