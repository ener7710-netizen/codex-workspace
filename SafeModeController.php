<?php
declare(strict_types=1);

namespace SEOJusAI\Rest\Controllers;

use WP_REST_Request;
use WP_REST_Response;
use SEOJusAI\Input\Input;
use SEOJusAI\Rest\RestKernel;
use SEOJusAI\Rest\AbstractRestController;
use SEOJusAI\Rest\Contracts\RestControllerInterface;
use SEOJusAI\Capabilities\CapabilityGuard;
use SEOJusAI\Capabilities\CapabilityMap;
use SEOJusAI\Safety\SafeMode;

defined('ABSPATH') || exit;

final class SafeModeController extends AbstractRestController implements RestControllerInterface {

    public function register_routes(): void {
        register_rest_route('seojusai/v1', '/safe-mode', [
            'methods'             => 'GET',
            'permission_callback' => [ RestKernel::class, 'can_manage' ],
            'callback'            => [ $this, 'status' ],
        ]);

        register_rest_route('seojusai/v1', '/safe-mode', [
            'methods'             => 'POST',
            'permission_callback' => [ RestKernel::class, 'can_manage' ],
            'callback'            => [ $this, 'toggle' ],
        ]);
    }

    public function status(WP_REST_Request $request): WP_REST_Response {
        return $this->ok([
            'enabled' => SafeMode::is_enabled(),
        ]);
    }

    public function toggle(WP_REST_Request $request): WP_REST_Response {
        if (!CapabilityGuard::can(CapabilityMap::MANAGE_SETTINGS)) {
            return new WP_REST_Response(['ok' => false, 'error' => 'Forbidden'], 403);
        }

        $enabled = (bool) $request->get_param('enabled');
        if ($enabled) {
            SafeMode::enable();
        } else {
            SafeMode::disable();
        }

        return $this->ok(['enabled' => SafeMode::is_enabled()]);
    }
}
