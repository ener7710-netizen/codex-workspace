<?php
declare(strict_types=1);

namespace SEOJusAI\Schema;

defined('ABSPATH') || exit;

/**
 * SchemaExtractor
 *
 * Витягує та нормалізує Schema.org (JSON-LD)
 * з rendered HTML сторінки.
 *
 * ❗ НЕ аналізує
 * ❗ НЕ робить висновків
 * ❗ ТІЛЬКИ ФАКТИ
 */
final class SchemaExtractor {

	/**
	 * Головна точка входу
	 *
	 * @return array{
	 *   types: string[],
	 *   has_author: bool,
	 *   has_publisher: bool,
	 *   has_faq: bool,
	 *   has_local_business: bool,
	 *   has_attorney: bool,
	 *   raw: array
	 * }
	 */
	public function extract(string $html): array {

		if ($html === '') {
			return $this->empty_result();
		}

		$json_blocks = $this->extract_jsonld_blocks($html);
		if (empty($json_blocks)) {
			return $this->empty_result();
		}

		$types = [];
		$has_author = false;
		$has_publisher = false;

		foreach ($json_blocks as $block) {
			$this->collect_types_recursive($block, $types);

			if (isset($block['author'])) {
				$has_author = true;
			}
			if (isset($block['publisher'])) {
				$has_publisher = true;
			}
		}

		$types = array_values(array_unique($types));

		return [
			'types'              => $types,
			'has_author'         => $has_author,
			'has_publisher'      => $has_publisher,
			'has_faq'            => in_array('FAQPage', $types, true),
			'has_local_business' => in_array('LocalBusiness', $types, true),
			'has_attorney'       => in_array('Attorney', $types, true),
			'raw'                => $json_blocks,
		];
	}

	/* =========================================================
	 * INTERNAL
	 * ========================================================= */

	private function extract_jsonld_blocks(string $html): array {

		if (!preg_match_all(
			'~<script[^>]+type=["\']application/ld\+json["\'][^>]*>(.*?)</script>~is',
			$html,
			$matches
		)) {
			return [];
		}

		$result = [];

		foreach ($matches[1] as $json) {
			$data = json_decode(trim((string) $json), true);
			if (is_array($data)) {
				$result[] = $data;
			}
		}

		return $result;
	}

	private function collect_types_recursive(array $data, array &$types): void {

		if (isset($data['@type'])) {
			if (is_string($data['@type'])) {
				$types[] = $data['@type'];
			} elseif (is_array($data['@type'])) {
				foreach ($data['@type'] as $t) {
					if (is_string($t)) {
						$types[] = $t;
					}
				}
			}
		}

		foreach ($data as $value) {
			if (is_array($value)) {
				$this->collect_types_recursive($value, $types);
			}
		}
	}

	private function empty_result(): array {
		return [
			'types'              => [],
			'has_author'         => false,
			'has_publisher'      => false,
			'has_faq'            => false,
			'has_local_business' => false,
			'has_attorney'       => false,
			'raw'                => [],
		];
	}
}
