<?php
declare(strict_types=1);

namespace SEOJusAI\Rest\Controllers;

use SEOJusAI\Rest\RestKernel;
use SEOJusAI\Rest\Contracts\RestControllerInterface;
use SEOJusAI\Capabilities\CapabilityGuard;
use SEOJusAI\Capabilities\CapabilityMap;
use SEOJusAI\Features\FeatureRegistry;
use SEOJusAI\Features\FeatureResolver;
use WP_REST_Request;
use WP_REST_Response;
use SEOJusAI\Input\Input;
use WP_Error;

defined('ABSPATH') || exit;

final class FeatureFlagsController implements RestControllerInterface {

    public function register_routes(): void {

        register_rest_route('seojusai/v1', '/features', [
            [
                'methods'             => 'GET',
                'callback'            => [$this, 'list'],
                'permission_callback' => [ RestKernel::class, 'can_execute' ],
            ],
            [
                'methods'             => 'POST',
                'callback'            => [$this, 'update'],
                'permission_callback' => [ RestKernel::class, 'can_manage' ],
            ],
        ]);

        register_rest_route('seojusai/v1', '/features/audit', [
            [
                'methods'             => 'GET',
                'callback'            => [$this, 'audit'],
                'permission_callback' => [ RestKernel::class, 'can_manage' ],
            ],
        ]);
    }

    public function can_view(): bool {
        return CapabilityGuard::can(CapabilityMap::MANAGE_SETTINGS);
    }

    public function can_manage(): bool {
        return CapabilityGuard::can(CapabilityMap::MANAGE_FEATURES) || CapabilityGuard::can(CapabilityMap::MANAGE_SETTINGS);
    }

    public function list(WP_REST_Request $req) {
        FeatureResolver::ensure_defaults();
        $values = FeatureResolver::get_all();
        $meta = [];
        foreach (FeatureRegistry::all() as $flag) {
            $meta[$flag->key] = [
                'title' => $flag->title,
                'description' => $flag->description,
                'stability' => $flag->stability,
                'default' => $flag->default,
                'since' => $flag->since,
                'enabled' => (bool)($values[$flag->key] ?? false),
            ];
        }
        return new WP_REST_Response(['flags' => $meta], 200);
    }

    public function update(WP_REST_Request $req) {
        FeatureResolver::ensure_defaults();
        $key = sanitize_key((string)($req->get_param('key') ?? ''));
        $enabled = (bool)($req->get_param('enabled') ?? false);
        $note = sanitize_text_field((string)($req->get_param('note') ?? ''));
        if (!$key || !FeatureRegistry::exists($key)) {
            return new WP_Error('invalid_flag', 'Невідомий прапорець функції.', ['status'=>400]);
        }
        $ok = FeatureResolver::set($key, $enabled, get_current_user_id(), $note);
        return new WP_REST_Response(['ok'=>$ok,'key'=>$key,'enabled'=>$enabled], $ok ? 200 : 500);
    }

    public function audit(WP_REST_Request $req) {
        $log = FeatureResolver::audit_log();
        return new WP_REST_Response(['items'=>$log], 200);
    }
}
