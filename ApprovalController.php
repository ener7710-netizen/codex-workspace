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
use SEOJusAI\Safety\ApprovalService;

defined('ABSPATH') || exit;

final class ApprovalController extends AbstractRestController implements RestControllerInterface {

    public function register_routes(): void {

        register_rest_route('seojusai/v1', '/approval/status', [
            'methods'             => 'GET',
            'permission_callback' => [ RestKernel::class, 'can_manage' ],
            'callback'            => [ $this, 'status' ],
        ]);

        register_rest_route('seojusai/v1', '/approval/approve', [
            'methods'             => 'POST',
            'permission_callback' => [ RestKernel::class, 'can_manage' ],
            'callback'            => [ $this, 'approve' ],
        ]);

        register_rest_route('seojusai/v1', '/approval/revoke', [
            'methods'             => 'POST',
            'permission_callback' => [ RestKernel::class, 'can_manage' ],
            'callback'            => [ $this, 'revoke' ],
        ]);
    }

    public function status(WP_REST_Request $request): WP_REST_Response {
        $decision_id = (string) $request->get_param('decision_id');
        return $this->ok([
            'decision_id' => $decision_id,
            'approved'    => ApprovalService::is_approved($decision_id),
        ]);
    }

    public function approve(WP_REST_Request $request): WP_REST_Response {
        if (!CapabilityGuard::can(CapabilityMap::APPROVE_CHANGES)) {
            return new WP_REST_Response(['ok' => false, 'error' => 'Forbidden'], 403);
        }
        $decision_id = (string) $request->get_param('decision_id');
        $user_id     = get_current_user_id();
        ApprovalService::approve($decision_id, $user_id);
        return $this->ok(['decision_id' => $decision_id, 'approved' => true]);
    }

    public function revoke(WP_REST_Request $request): WP_REST_Response {
        if (!CapabilityGuard::can(CapabilityMap::APPROVE_CHANGES)) {
            return new WP_REST_Response(['ok' => false, 'error' => 'Forbidden'], 403);
        }
        $decision_id = (string) $request->get_param('decision_id');
        ApprovalService::revoke($decision_id);
        return $this->ok(['decision_id' => $decision_id, 'approved' => false]);
    }
}
