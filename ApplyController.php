<?php
declare(strict_types=1);

namespace SEOJusAI\Rest\Controllers;

use WP_REST_Request;
use WP_REST_Response;
use SEOJusAI\Input\Input;
use SEOJusAI\Core\EmergencyStop;
use SEOJusAI\Executors\ApplyService;
use SEOJusAI\Executors\ExecutorResolver;
use SEOJusAI\Rest\RestKernel;
use SEOJusAI\Capabilities\CapabilityGuard;
use SEOJusAI\Capabilities\CapabilityMap;
use SEOJusAI\Safety\SafeMode;
use SEOJusAI\Safety\ApprovalService;
use SEOJusAI\Snapshots\SnapshotService;
use SEOJusAI\AI\DecisionContract;
use SEOJusAI\Decisions\DecisionRepository;
use SEOJusAI\Rest\Contracts\RestControllerInterface;

defined('ABSPATH') || exit;

final class ApplyController implements RestControllerInterface {

	public function register_routes(): void {
		register_rest_route('seojusai/v1', '/apply', [
			'methods'             => 'POST',
			'permission_callback' => [RestKernel::class, 'can_execute'],
			'callback'            => [$this, 'apply_decision'],
			'args'                => [
				'post_id' => [
					'type'     => 'integer',
					'required' => true,
				],
				'decision' => [
					'type'     => 'object',
					'required' => true,
				],
			],
		]);
	}

	public function apply_decision(WP_REST_Request $request): WP_REST_Response {

		if ( EmergencyStop::is_active() ) {
			return new WP_REST_Response(['error' => 'Emergency stop active'], 423);
		}

		if (class_exists(SafeMode::class) && SafeMode::is_enabled()) {
			return new WP_REST_Response(['error' => 'Safe mode enabled'], 423);
		}

		$post_id  = Input::int((int) $request->get_param('post_id'), 0);

		if (!CapabilityGuard::can(CapabilityMap::APPLY_CHANGES, ['post_id' => $post_id])) {
			return new WP_REST_Response(['error' => 'Forbidden'], 403);
		}


		if ( $post_id > 0 && ! current_user_can('edit_post', $post_id) ) {
			return new WP_REST_Response(['error' => 'Forbidden'], 403);
		}
		$decision = $request->get_param('decision');
		$decision_arr = is_array($decision) ? $decision : (array) $decision;
		$decision = $decision_arr;
		$decision_id = (string) ($decision_arr['id'] ?? ($request->get_param('decision_id') ?? ''));
		$requires_approval = (bool) ($decision_arr['requires_approval'] ?? false);
		$action = (string) ($decision_arr['action'] ?? '');

		// High-risk actions require approval by default
		if (!$requires_approval && in_array($action, ['redirect', 'schema_apply', 'robots', 'settings'], true)) {
			$requires_approval = true;
		}

		if ($requires_approval && !CapabilityGuard::can(CapabilityMap::APPROVE_CHANGES)) {
			if (!ApprovalService::is_approved($decision_id)) {
				return new WP_REST_Response([
					'error' => 'Approval required',
					'decision_id' => $decision_id,
					'requires_approval' => true,
				], 409);
			}
		}


		if ( ! DecisionContract::validate($decision_arr) ) {
			return new WP_REST_Response(['error' => 'Invalid Decision Contract'], 422);
		}

		// 🧷 Safety snapshot
		$snapshot_id = (new SnapshotService())->capture_post($post_id, 'pre_apply');

		if ( ! $snapshot_id ) {
			return new WP_REST_Response(['error' => 'Failed to create safety snapshot'], 500);
		}

		// 🔒 Mutex: не допускаємо подвійне застосування одного рішення
		$hash = (string) ($decision_arr['decision_hash'] ?? ($decision_arr['hash'] ?? ''));
		$hash = $hash !== '' ? sanitize_text_field($hash) : md5(wp_json_encode($decision_arr));
		$lock_key = 'seojusai_apply_lock_' . $post_id . '_' . $hash;
		if ( get_transient($lock_key) ) {
			return new WP_REST_Response(['error' => 'Decision already applying'], 409);
		}
		set_transient($lock_key, 1, 60);

// 🧠 Decision ядро: створюємо запис рішення перед застосуванням
$decision_record_id = 0;
if (class_exists(DecisionRepository::class)) {
	$decision_record_id = (new DecisionRepository())->create((array)$decision, [
		'post_id' => $post_id,
		'source' => 'rest_apply',
		'context_type' => 'page',
		'meta' => [
			'user_id' => get_current_user_id(),
		],
	]);
	do_action('seojusai/decision/created', ['decision_id' => $decision_record_id, 'post_id' => $post_id, 'source' => 'rest_apply']);
}


		$success = false;
		$handled = false;

		/**
		 * 🔑 ЄДИНИЙ шлях виконання:
		 * - якщо рішення у форматі single-action (action/type) і executor підтримує — виконуємо executor
		 * - інакше — ApplyService (batch actions)
		 */
		if ( class_exists(ExecutorResolver::class) && is_array($decision_arr) && isset($decision_arr['action']) && !isset($decision_arr['actions']) ) {
			(new ExecutorResolver())->register();

			$task = [
				'action'      => (string) ($decision['action'] ?? ''),
				'type'        => (string) ($decision['type'] ?? ''),
				'post_id'     => $post_id,
				'decision'    => $decision_arr,
				'snapshot_id' => $snapshot_id,
				'decision_record_id' => $decision_record_id,
				'source'      => 'rest_apply',
				'created_at'  => time(),
				'auto'        => true,
			];

			do_action('seojusai/executor/run_task', $task);
			$handled = true;
			$success = true;
		}

		if ( ! $handled ) {
			$service = new ApplyService();
			$success = $service->apply($decision_arr, [
				'post_id'      => $post_id,
				'snapshot_id'  => $snapshot_id,
				'decision_record_id' => $decision_record_id,
			]);
		}

		delete_transient($lock_key);

		return new WP_REST_Response([
			'success'      => (bool) $success,
			'snapshot_id'  => $snapshot_id,
				'decision_record_id' => $decision_record_id,
			'handled_by'   => $handled ? 'executor' : 'apply_service',
		], $success ? 200 : 500);
	}
}
