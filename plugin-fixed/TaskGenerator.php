<?php
declare(strict_types=1);

namespace SEOJusAI\AI;

use SEOJusAI\Core\EmergencyStop;

defined('ABSPATH') || exit;

final class TaskGenerator {

	/**
	 * Генерує список задач на основі аналізу.
	 *
	 * @param array<string,mixed> $analysis
	 * @return array<int,array<string,mixed>>
	 */
	public function generate(array $analysis): array {

		if ( EmergencyStop::is_active() ) {
			return [];
		}

		$tasks   = [];
		$post_id = (int) ($analysis['post_id'] ?? 0);

		if ( $post_id <= 0 ) {
			return [];
		}

		/* ==========================================================
		 * CONTENT GAP (SERP vs PAGE STRUCTURE)
		 * ========================================================== */

		$serp_structure = $analysis['compare']['serp']['structure_cloud'] ?? [];
		$my_h2          = (array) ($analysis['compare']['page']['h2'] ?? []);

		foreach ( $serp_structure as $item ) {

			if (
				empty($item['text']) ||
				in_array($item['text'], $my_h2, true)
			) {
				continue;
			}

			$tasks[] = [
				'action'   => 'add_section',
				'level'    => $item['level'] ?? 'h2',
				'title'    => $item['text'],
				'post_id'  => $post_id,
				'auto'     => false,
				'priority' => (($item['level'] ?? '') === 'h2') ? 'high' : 'medium',
				'source'   => 'ai:content_gap',
			];
		}

		/* ==========================================================
		 * SCHEMA GAP (NEW PIPELINE)
		 * ========================================================== */

		if (
			! empty($analysis['schema']) &&
			is_array($analysis['schema']) &&
			! empty($analysis['schema']['gap']) &&
			is_array($analysis['schema']['gap'])
		) {

			$gap = $analysis['schema']['gap'];

			if (
				! empty($gap['missing']) &&
				is_array($gap['missing'])
			) {
				foreach ( $gap['missing'] as $schemaType ) {

					if ( ! is_string($schemaType) || $schemaType === '' ) {
						continue;
					}

					$tasks[] = [
						'action'   => 'add_schema',
						'type'     => $schemaType,
						'post_id'  => $post_id,
						'auto'     => in_array($schemaType, ['Attorney', 'LegalService'], true),
						'priority' => 'high',
						'source'   => 'ai:schema_gap',
					];
				}
			}
		}

		/* ==========================================================
		 * LEGACY SCHEMA GAP (BACKWARD COMPATIBILITY)
		 * ========================================================== */

		if (
			empty($analysis['schema']) &&
			! empty($analysis['gaps']['missing_schema']) &&
			is_array($analysis['gaps']['missing_schema'])
		) {
			foreach ( $analysis['gaps']['missing_schema'] as $schema ) {

				if ( ! is_string($schema) || $schema === '' ) {
					continue;
				}

				$tasks[] = [
					'action'   => 'add_schema',
					'type'     => $schema,
					'post_id'  => $post_id,
					'auto'     => in_array($schema, ['Attorney', 'LegalService'], true),
					'priority' => 'high',
					'source'   => 'ai:schema_gap_legacy',
				];
			}
		}

		return $tasks;
	}
}
