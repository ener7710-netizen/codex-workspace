<?php
declare(strict_types=1);

namespace SEOJusAI\Autopilot;

use SEOJusAI\Core\EmergencyStop;
use SEOJusAI\Core\ModuleRegistry;
use SEOJusAI\AI\DecisionResult;
use SEOJusAI\Executors\ApplyService;
use SEOJusAI\Snapshots\SnapshotService;
use SEOJusAI\Features\FeatureResolver;
use SEOJusAI\Autopilot\AutopilotReliability;

defined('ABSPATH') || exit;

/**
 * AutopilotEngine
 *
 * 🧩 РОЛЬ:
 * Оркестратор режимів (shadow / limited / full).
 * Приймає рішення AI та трансформує їх у завдання для людини.
 *
 * ❗ НЕ застосовує зміни напряму.
 */
final class AutopilotEngine {

	private const OPTION_KEY = 'seojusai_autopilot';

	private ModuleRegistry $modules;
	private bool $registered = false;

	public function __construct() {
		$this->modules = ModuleRegistry::instance();
	}

	/**
	 * Підключення до ядра.
	 */
	public function register(): void {

		if ( $this->registered ) {
			return;
		}
		$this->registered = true;

		// 🔁 Старий контракт (array) — залишаємо
		add_action('seojusai/ai/decision', [$this, 'on_ai_decision'], 20, 1);

		// ✅ Новий контракт (DecisionResult)
		add_action('seojusai/decision/result', [$this, 'on_decision_result'], 20, 1);
	}

	/* ==========================================================
	 * НОВИЙ ОБРОБНИК (РЕКОМЕНДОВАНИЙ)
	 * ========================================================== */

	public function on_decision_result(DecisionResult $decision): void {

		if ( EmergencyStop::is_active() ) {
			return;
		}

		if ( ! $this->modules->is_enabled('autopilot') ) {
			return;
		}

		// 🔒 Reliability pause gate
		if (class_exists(AutopilotReliability::class) && AutopilotReliability::is_paused()) {
			$this->log('paused_skip', [
				'post_id' => $decision->post_id,
				'type' => $decision->type,
				'hash' => $decision->hash,
			]);
			return;
		}

		$mode = $this->get_mode();

		// SHADOW — тільки лог
		if ( $mode === 'shadow' ) {
			$this->log('shadow', [
				'type'          => $decision->type,
				'post_id'       => $decision->post_id,
				'decision_hash' => $decision->hash,
			]);
			return;
		}

		// FULL SAFE → auto-apply allowlisted low-risk actions
		if ( $mode === 'full' ) {
			$min_conf = 0.70;
			if (class_exists(AutopilotReliability::class)) {
				$thr = AutopilotReliability::thresholds();
				$min_conf = (float)($thr['min_confidence'] ?? 0.70);
			}
			$conf = class_exists(AutopilotReliability::class) ? AutopilotReliability::extract_confidence($decision->decision) : 0.0;
			if ($conf >= $min_conf && $this->try_auto_apply($decision)) {
				return;
			}
		}

		// LIMITED / FULL → створюємо review task
		$this->enqueue_review_task([
			'type'     => $decision->type,
			'post_id'  => $decision->post_id,
			'decision' => $decision->decision,
		], $decision->hash);
	}

	/* ==========================================================
	 * СТАРИЙ ОБРОБНИК (BACKWARD COMPATIBILITY)
	 * ========================================================== */

	/**
	 * @param array<string,mixed> $payload
	 */
	public function on_ai_decision(array $payload): void {

		if ( EmergencyStop::is_active() ) {
			return;
		}

		if ( ! $this->modules->is_enabled('autopilot') ) {
			return;
		}

		// 🔒 Reliability pause gate
		if (class_exists(AutopilotReliability::class) && AutopilotReliability::is_paused()) {
			$this->log('paused_skip', [
				'post_id' => (int)($payload['post_id'] ?? 0),
				'type' => (string)($payload['type'] ?? ''),
			]);
			return;
		}

		$type     = isset($payload['type']) ? sanitize_key((string) $payload['type']) : '';
		$post_id  = (int) ($payload['post_id'] ?? 0);
		$decision = $payload['decision'] ?? null;

		if ( $type === '' || ! is_array($decision) ) {
			return;
		}

		$decision_hash = hash(
			'sha256',
			(string) wp_json_encode($decision, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
		);

		$this->enqueue_review_task($payload, $decision_hash);
	}

	/* ==========================================================
	 * TASK QUEUE
	 * ========================================================== */

	private function enqueue_review_task(array $payload, string $decision_hash): void {

		$post_id = (int) ($payload['post_id'] ?? 0);

		$task = [
			'action'        => 'review_decision',
			'post_id'       => $post_id,
			'type'          => sanitize_key((string) ($payload['type'] ?? '')),
			'decision_hash' => $decision_hash,
			'decision'      => $payload['decision'] ?? [],
			'source'        => 'autopilot',
			'created_at'    => time(),
			'auto'          => false,
			'priority'      => 'high',
		];

		do_action('seojusai/tasks/enqueue', [$task]);
		do_action('seojusai/autopilot/task_enqueued', $task);
	}

	/* ==========================================================
	 * SETTINGS
	 * ========================================================== */

	public function get_mode(): string {

		$opt  = get_option(self::OPTION_KEY, []);
		$mode = is_array($opt) ? (string) ($opt['mode'] ?? 'shadow') : 'shadow';
		$mode = sanitize_key($mode);

		return in_array($mode, ['shadow', 'limited', 'full'], true)
			? $mode
			: 'shadow';
	}


public function set_mode(string $mode): void {
	$mode = sanitize_key($mode);
	if ( ! in_array($mode, ['shadow','limited','full'], true) ) {
		$mode = 'shadow';
	}
	$opt = get_option(self::OPTION_KEY, []);
	if ( ! is_array($opt) ) $opt = [];
	$opt['mode'] = $mode;
	update_option(self::OPTION_KEY, $opt, false);
}

public function set_allow_apply(bool $allow): void {
	$opt = get_option(self::OPTION_KEY, []);
	if ( ! is_array($opt) ) $opt = [];
	$opt['allow_apply'] = $allow ? 1 : 0;
	update_option(self::OPTION_KEY, $opt, false);
}


	public function is_apply_allowed(): bool {

		if ( EmergencyStop::is_active() ) {
			return false;
		}

		$opt = get_option(self::OPTION_KEY, []);

		return is_array($opt) && ! empty($opt['allow_apply']);
	}



public function is_full_safe_enabled(): bool {
	// Feature flag gate + allow_apply option + mode FULL
	if ( ! class_exists(FeatureResolver::class) ) {
		return false;
	}
	if ( ! FeatureResolver::enabled('autopilot_full_safe_mode_v1') ) {
		return false;
	}
	if ( ! $this->is_apply_allowed() ) {
		return false;
	}
	return $this->get_mode() === 'full';
}

/**
 * Спроба автоматичного застосування (SAFE).
 * Повертає true якщо застосовано.
 */
private function try_auto_apply(DecisionResult $decision): bool {

	if ( ! $this->is_full_safe_enabled() ) {
		return false;
	}

	$post_id = (int) $decision->post_id;
	if ( $post_id <= 0 ) return false;

	$policy = new AutopilotPolicy();
	if ( ! $policy->can_auto_apply($decision->decision, ['post_id' => $post_id]) ) {
		return false;
	}

	// Snapshot first (rollback always)
	$snap = new SnapshotService();
	$snapshot_id = $snap->capture_post($post_id, 'autopilot', [
		'decision_hash' => $decision->hash,
		'type' => $decision->type,
	]);

	if ( $snapshot_id <= 0 ) {
		return false;
	}

	$apply = new ApplyService();
	$ok = $apply->apply($decision->decision, [
		'post_id' => $post_id,
		'snapshot_id' => $snapshot_id,
	]);

	if ( $ok ) {
		$this->log('auto_applied', [
			'post_id' => $post_id,
			'type' => $decision->type,
			'hash' => $decision->hash,
			'snapshot_id' => $snapshot_id,
		]);
		do_action('seojusai/autopilot/auto_applied', [
			'post_id' => $post_id,
			'type' => $decision->type,
			'hash' => $decision->hash,
			'snapshot_id' => $snapshot_id,
		]);
		return true;
	}

	$this->log('auto_apply_failed', [
		'post_id' => $post_id,
		'type' => $decision->type,
		'hash' => $decision->hash,
		'snapshot_id' => $snapshot_id,
	]);
	return false;
}

/* ==========================================================
	 * LOGGING
	 * ========================================================== */

	private function log(string $event, array $data = []): void {

		do_action('seojusai/autopilot/log', array_merge(
			[
				'event'     => sanitize_key($event),
				'timestamp' => time(),
			],
			$data
		));
	}
}
