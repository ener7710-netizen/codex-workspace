<?php
declare(strict_types=1);

namespace SEOJusAI\Rest\Controllers;

use SEOJusAI\Rest\RestKernel;
use SEOJusAI\Bulk\BulkJobRepository;
use SEOJusAI\Bulk\BulkPlanner;
use SEOJusAI\Capabilities\CapabilityGuard;
use SEOJusAI\Safety\SafeMode;
use WP_REST_Request;
use WP_REST_Response;
use SEOJusAI\Input\Input;
use WP_Error;

defined('ABSPATH') || exit;

final class BulkController {

    public function register_routes(): void {

        register_rest_route('seojusai/v1', '/bulk/jobs', [
            [
                'methods'             => 'GET',
                'callback'            => [ $this, 'list_jobs' ],
                'permission_callback' => [ $this, 'can_view' ],
            ],
        ]);

        register_rest_route('seojusai/v1', '/bulk/jobs/(?P<id>\d+)', [
            [
                'methods'             => 'GET',
                'callback'            => [ $this, 'get_job' ],
                'permission_callback' => [ $this, 'can_view' ],
            ],
        ]);



register_rest_route('seojusai/v1', '/bulk/jobs/(?P<id>\d+)/approval', [
    [
        'methods'             => 'GET',
        'callback'            => [ $this, 'approval_status' ],
        'permission_callback' => [ $this, 'can_view' ],
    ],
]);

register_rest_route('seojusai/v1', '/bulk/jobs/(?P<id>\d+)/approve', [
    [
        'methods'             => 'POST',
        'callback'            => [ $this, 'approve_job' ],
        'permission_callback' => [ $this, 'can_approve' ],
    ],
]);

register_rest_route('seojusai/v1', '/bulk/jobs/(?P<id>\d+)/revoke', [
    [
        'methods'             => 'POST',
        'callback'            => [ $this, 'revoke_job' ],
        'permission_callback' => [ $this, 'can_approve' ],
    ],
]);

register_rest_route('seojusai/v1', '/bulk/audit', [
            [
                'methods'             => 'POST',
                'callback'            => [ $this, 'start_audit' ],
                'permission_callback' => [ $this, 'can_audit' ],
            ],
        ]);

        register_rest_route('seojusai/v1', '/bulk/apply', [
            [
                'methods'             => 'POST',
                'callback'            => [ $this, 'start_apply' ],
                'permission_callback' => [ $this, 'can_apply' ],
            ],
        ]);

        register_rest_route('seojusai/v1', '/bulk/rollback', [
            [
                'methods'             => 'POST',
                'callback'            => [ $this, 'start_rollback' ],
                'permission_callback' => [ $this, 'can_apply' ],
            ],
        ]);

        register_rest_route('seojusai/v1', '/bulk/jobs/(?P<id>\d+)/action', [
            [
                'methods'             => 'POST',
                'callback'            => [ $this, 'job_action' ],
                'permission_callback' => [ $this, 'can_apply' ],
            ],
        ]);
    }

    public function can_view(WP_REST_Request $req): bool {
        return RestKernel::can_execute($req) && CapabilityGuard::can('seojusai_view_reports');
    }

    public function can_audit(WP_REST_Request $req): bool {
        return RestKernel::can_execute($req) && CapabilityGuard::can('seojusai_run_analysis');
    }

    public function can_apply(WP_REST_Request $req): bool {
        return RestKernel::can_execute($req) && CapabilityGuard::can('seojusai_apply_changes');
    }

    public function can_approve(WP_REST_Request $req): bool {
        return RestKernel::can_execute($req) && (CapabilityGuard::can('seojusai_approve_changes') || CapabilityGuard::can('seojusai_apply_changes'));
    }

    public function list_jobs(WP_REST_Request $req) {
        $repo = new BulkJobRepository();
        $limit = min(50, max(1, (int)($req->get_param('limit') ?? 20)));
        return new WP_REST_Response($repo->list($limit), 200);
    }

    public function get_job(WP_REST_Request $req) {
        $repo = new BulkJobRepository();
        $id = (int)$req['id'];
        $job = $repo->get($id);
        if (!$job) {
            return new WP_Error('not_found', 'Bulk job not found', ['status'=>404]);
        }
        return new WP_REST_Response($job, 200);
    }

    public function start_audit(WP_REST_Request $req) {
        $filters = $this->read_filters($req);
        $repo = new BulkJobRepository();
        $job_id = $repo->create('audit', $filters, get_current_user_id());
        $repo->set_status($job_id, 'running');
        $planner = new BulkPlanner();
        $planner->plan_audit($job_id, $filters);
        return new WP_REST_Response(['job_id'=>$job_id], 200);
    }

    public function start_apply(WP_REST_Request $req) {
        if (SafeMode::is_active()) {
            return new WP_Error('safe_mode', 'Safe mode enabled', ['status'=>409]);
        }
        $filters = $this->read_filters($req);
        $repo = new BulkJobRepository();
        $job_id = $repo->create('apply', $filters, get_current_user_id());
        $repo->set_status($job_id, 'awaiting_approval');
        return new WP_REST_Response(['job_id'=>$job_id,'needs_approval'=>true], 200);
    }

    public function start_rollback(WP_REST_Request $req) {
        if (SafeMode::is_active()) {
            return new WP_Error('safe_mode', 'Safe mode enabled', ['status'=>409]);
        }
        $filters = $this->read_filters($req);
        $repo = new BulkJobRepository();
        $job_id = $repo->create('rollback', $filters, get_current_user_id());
        $repo->set_status($job_id, 'awaiting_approval');
        return new WP_REST_Response(['job_id'=>$job_id,'needs_approval'=>true], 200);
    }

    public function job_action(WP_REST_Request $req) {
        $id = (int)$req['id'];
        $action = sanitize_key((string)$req->get_param('action'));
        $repo = new BulkJobRepository();
        $job = $repo->get($id);
        if (!$job) {
            return new WP_Error('not_found', 'Bulk job not found', ['status'=>404]);
        }

        if ($action === 'pause') $repo->set_status($id, 'paused');
        if ($action === 'resume') $repo->set_status($id, 'running');
        if ($action === 'cancel') $repo->set_status($id, 'cancelled');

        return new WP_REST_Response(['ok'=>true,'status'=>$repo->get($id)['status'] ?? ''], 200);
    }



public function approval_status(WP_REST_Request $req) {
    $id = (int)$req['id'];
    $repo = new BulkJobRepository();
    $job = $repo->get($id);
    if (!$job) return new WP_Error('not_found', 'Bulk job not found', ['status'=>404]);
    $approved = false;
    $until = (string)($job['approved_until'] ?? '');
    if ($until && strtotime($until) > time()) $approved = true;
    return new WP_REST_Response([
        'job_id' => $id,
        'approved' => $approved,
        'approved_by' => (int)($job['approved_by'] ?? 0),
        'approved_until' => $until,
        'status' => (string)($job['status'] ?? ''),
    ], 200);
}

public function approve_job(WP_REST_Request $req) {
    if (SafeMode::is_active()) {
        return new WP_Error('safe_mode', 'Safe mode enabled', ['status'=>409]);
    }
    $id = (int)$req['id'];
    $note = sanitize_text_field((string)($req->get_param('note') ?? ''));
    $repo = new BulkJobRepository();
    $job = $repo->get($id);
    if (!$job) return new WP_Error('not_found', 'Bulk job not found', ['status'=>404]);
    $type = (string)($job['job_type'] ?? '');
    if (!in_array($type, ['apply','rollback'], true)) {
        return new WP_Error('invalid_job', 'This job does not require approval', ['status'=>400]);
    }
    // approve and plan
    $repo->approve($id, get_current_user_id(), 172800, $note ?: null);
    $filters = (array)($job['filters'] ?? []);
    $planner = new BulkPlanner();
    if ($type === 'apply') $planner->plan_apply($id, $filters);
    if ($type === 'rollback') $planner->plan_rollback($id, $filters);
    return new WP_REST_Response(['ok'=>true,'job_id'=>$id,'status'=>'running'], 200);
}

public function revoke_job(WP_REST_Request $req) {
    $id = (int)$req['id'];
    $repo = new BulkJobRepository();
    $job = $repo->get($id);
    if (!$job) return new WP_Error('not_found', 'Bulk job not found', ['status'=>404]);
    $type = (string)($job['job_type'] ?? '');
    if (!in_array($type, ['apply','rollback'], true)) {
        return new WP_Error('invalid_job', 'This job does not require approval', ['status'=>400]);
    }
    // we only allow revoke if tasks haven't been scheduled yet
    $status = (string)($job['status'] ?? '');
    if ($status === 'running' && (int)($job['processed_items'] ?? 0) > 0) {
        return new WP_Error('cannot_revoke', 'Job already started', ['status'=>409]);
    }
    $repo->revoke_approval($id);
    return new WP_REST_Response(['ok'=>true,'job_id'=>$id,'status'=>'awaiting_approval'], 200);
}

private function read_filters(WP_REST_Request $req): array {
        $__parsed = Input::json_array_strict((string) $req->get_body(), 300000);
		if (!$__parsed['ok']) return [];
		$filters = (array) (($__parsed['data']['filters'] ?? []) );
		// whitelist
        $out = [];
        $out['post_types'] = array_values(array_filter(array_map('sanitize_key', (array)($filters['post_types'] ?? ['post','page']))));
        $out['statuses']   = array_values(array_filter(array_map('sanitize_key', (array)($filters['statuses'] ?? ['publish']))));
        $out['limit']      = min(2000, max(1, (int)($filters['limit'] ?? 200)));
        $out['only_noindex'] = !empty($filters['only_noindex']);
        return $out;
    }
}
