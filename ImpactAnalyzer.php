<?php
declare(strict_types=1);

namespace SEOJusAI\Impact;

defined('ABSPATH') || exit;

final class ImpactAnalyzer {

	/**
	 * Записує зміни в базу даних.
	 */
	public function record(
		string $action,
		string $entity_type,
		int $entity_id,
		array $before,
		array $after,
		array $meta = []
	): void {
		global $wpdb;

		// Розраховуємо різницю (дифф)
		$diff = $this->calculate_diff($before, $after);

		$wpdb->insert(
			"{$wpdb->prefix}seojusai_impact",
			[
				'action_type' => $action,      // 'apply' або 'rollback'
				'entity_type' => $entity_type, // 'page'
				'entity_id'   => $entity_id,
				'content_before' => wp_json_encode($before),
				'content_after'  => wp_json_encode($after),
				'diff_summary'   => wp_json_encode($diff),
				'meta_data'      => wp_json_encode($meta),
				'created_at'     => current_time('mysql', true),
			],
			['%s', '%s', '%d', '%s', '%s', '%s', '%s', '%s']
		);
	}

	/**
	 * Простий метод для порівняння тексту та мета-даних.
	 */
	private function calculate_diff(array $before, array $after): array {
		$diff = [
			'title_changed'   => ($before['post']['post_title'] ?? '') !== ($after['post']['post_title'] ?? ''),
			'content_length_delta' => strlen($after['post']['post_content'] ?? '') - strlen($before['post']['post_content'] ?? ''),
			'meta_keys_changed'    => array_keys(array_diff_assoc($before['meta'] ?? [], $after['meta'] ?? []))
		];

		return $diff;
	}
}
