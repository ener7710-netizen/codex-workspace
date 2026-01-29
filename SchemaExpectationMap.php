<?php
declare(strict_types=1);

namespace SEOJusAI\Schema;

defined('ABSPATH') || exit;

/**
 * SchemaExpectationMap
 * ------------------------------------------------------------
 * ЕТАЛОН очікуваних Schema.org для сторінок юридичних послуг.
 *
 * ❗ НЕ аналізує
 * ❗ НЕ читає HTML
 * ❗ ТІЛЬКИ ОЧІКУВАННЯ
 */
final class SchemaExpectationMap {

	/**
	 * Отримати очікувані schema для сторінки
	 *
	 * @param string $page_type Напр. 'legal_service'
	 */
	public static function get(string $page_type = 'legal_service'): array {

		// MVP: сторінка послуги адвокатського обʼєднання
		if ($page_type === 'legal_service') {
			return [

				'Service' => [
					'required' => true,
					'fields' => [
						'@type'       => 'LegalService',
						'name'        => true,
						'provider'    => true,
						'areaServed'  => true,
						'priceRange'  => true,
					],
				],

				'FAQPage' => [
					'required' => true,
					'fields' => [
						'mainEntity' => true,
					],
				],

				'BreadcrumbList' => [
					'required' => true,
					'fields' => [
						'itemListElement' => true,
					],
				],

				'Attorney' => [
					'required' => false,
					'fields' => [
						'name'      => true,
						'jobTitle' => true,
						'sameAs'   => false,
					],
				],
			];
		}

		return [];
	}
}
