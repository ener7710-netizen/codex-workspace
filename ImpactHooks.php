<?php
declare(strict_types=1);

namespace SEOJusAI\Impact;

defined('ABSPATH') || exit;

/**
 * ImpactHooks
 *
 * Звʼязує Executors → ImpactService
 * ЄДИНЕ місце підписки на impact-події
 */
final class ImpactHooks {

	public static function register(): void {

		// AFTER APPLY
		add_action(
			'seojusai/apply/after',
			static function (array $context): void {

				$post_id     = (int) ($context['post_id'] ?? 0);
				$snapshot_id = (int) ($context['snapshot_id'] ?? 0);

				if ($post_id <= 0 || $snapshot_id <= 0) {
					return;
				}

				(new ImpactService())->record_apply($post_id, $snapshot_id);
			},
			10,
			1
		);

		// AFTER ROLLBACK
		add_action(
			'seojusai/rollback/after',
			static function (array $context): void {

				$post_id     = (int) ($context['post_id'] ?? 0);
				$snapshot_id = (int) ($context['snapshot_id'] ?? 0);

				if ($post_id <= 0 || $snapshot_id <= 0) {
					return;
				}

				(new ImpactService())->record_rollback($post_id, $snapshot_id);
			},
			10,
			1
		);
	}
}
