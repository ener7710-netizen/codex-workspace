<?php
declare(strict_types=1);

namespace SEOJusAI\Rest\Controllers;

use WP_REST_Request;
use WP_REST_Response;
use SEOJusAI\Calibration\CalibrationManager;

defined('ABSPATH') || exit;


final class CalibrationController extends \SEOJusAI\Rest\AbstractRestController
{
    public function register_routes(): void
    {
        register_rest_route('seojusai/v1', '/calibration/status/(?P<id>\\d+)', [
            'methods'  => 'GET',
            'callback' => [$this, 'get_status'],
            'permission_callback' => function () {
                return current_user_can('edit_posts');
            },
        ]);
    }

    public function get_status(WP_REST_Request $request): WP_REST_Response
    {
        $postId = (int) $request['id'];
        $manager = new CalibrationManager();

        return new WP_REST_Response([
            'postId'   => $postId,
            'stable'   => $manager->isStable($postId),
            'baseline' => (int) get_post_meta($postId, '_seojusai_baseline_frozen', true),
        ]);
    }
}
