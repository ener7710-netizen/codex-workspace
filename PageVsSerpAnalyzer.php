<?php
declare(strict_types=1);

namespace SEOJusAI\Crawl;

use SEOJusAI\SERP\SerpClient;
use SEOJusAI\SERP\FingerprintService;

defined('ABSPATH') || exit;

final class PageVsSerpAnalyzer {

	private SerpClient $serp;
	private FingerprintService $fingerprints;

	public function __construct() {
		$this->serp         = new SerpClient();
		$this->fingerprints = new FingerprintService();
	}

	public function compare(array $payload): array {
		$page = [
			'url'    => (string) ($payload['url'] ?? ''),
			'h1'     => (string) ($payload['h1'] ?? ''),
			'h2'     => (array)  ($payload['h2'] ?? []), // Ваші поточні теми
			'schema' => (array)  ($payload['schema'] ?? []),
		];

		$query = (string) ($payload['query'] ?? '');
		$serp_items = $query !== '' ? $this->serp->search($query, 10) : [];
		$fingerprints = $this->fingerprints->build_for_urls(array_column($serp_items, 'url'));

		$serp_structure = [];
		$schema_pool = [];

		foreach ($fingerprints as $fp) {
			if (!empty($fp['structure'])) {
				$serp_structure = array_merge($serp_structure, $fp['structure']);
			}
			if (!empty($fp['schema_types'])) {
				$schema_pool = array_merge($schema_pool, $fp['schema_types']);
			}
		}

		return [
			'page' => $page,
			'serp' => [
				'common_schema' => array_values(array_unique($schema_pool)),
				'structure_cloud' => $serp_structure, // Повна карта заголовків конкурентів
			],
			'gaps' => [
				'missing_schema' => array_values(array_diff($schema_pool, $page['schema'])),
				// Gemini тепер проаналізує structure_cloud і знайде пропущені послуги
				'missing_services' => true,
			]
		];
	}
}
