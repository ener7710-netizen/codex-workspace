<?php
declare(strict_types=1);

namespace SEOJusAI\Schema;

defined('ABSPATH') || exit;

/**
 * SchemaGapAnalyzer
 * ------------------------------------------------------------
 * Порівнює:
 *  - фактичні Schema (SchemaExtractor)
 *  - очікувані Schema (SchemaExpectationMap)
 *
 * Повертає ТІЛЬКИ дефіцити (GAP).
 */
final class SchemaGapAnalyzer {

	/**
	 * @param array $schema_facts результат SchemaExtractor
	 * @param string $page_type
	 *
	 * @return array<int, string> список warning-повідомлень
	 */
	public static function analyze(array $schema_facts, string $page_type = 'legal_service'): array {

		$expected = SchemaExpectationMap::get($page_type);
		$foundTypes = $schema_facts['types'] ?? [];

		$warnings = [];

		foreach ($expected as $schemaName => $config) {

			$isRequired = (bool) ($config['required'] ?? false);
			$fields     = (array) ($config['fields'] ?? []);

			$exists = in_array($schemaName, $foundTypes, true);

			if ($isRequired && ! $exists) {
				$warnings[] = "⚠️ Відсутня Schema {$schemaName}.";
				continue;
			}

			if ($exists && ! empty($fields)) {
				// тут ми НЕ ліземо глибоко — MVP
				foreach ($fields as $field => $required) {
					if ($required) {
						$warnings[] = "⚠️ У Schema {$schemaName} відсутній {$field}.";
					}
				}
			}
		}

		return $warnings;
	}
}
