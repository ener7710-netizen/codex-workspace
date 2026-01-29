<?php
declare(strict_types=1);

namespace SEOJusAI\AI;

use SEOJusAI\Crawl\PageVsSerpAnalyzer as CrawlPageVsSerpAnalyzer;
use SEOJusAI\Schema\SchemaStorage;

defined('ABSPATH') || exit;

/**
 * PageVsSerpAnalyzer (AI-layer wrapper)
 *
 * Роль:
 * - Зібрати факти про поточну сторінку (URL, H1/H2, Schema types)
 * - Запустити порівняння зі структурою SERP через Crawl\PageVsSerpAnalyzer
 * - Повернути стабільний analysis-масив для DecisionPipeline та REST
 */
final class PageVsSerpAnalyzer {

	private CrawlPageVsSerpAnalyzer $crawl;
	private SchemaStorage $schema;

	public function __construct() {
		$this->crawl  = new CrawlPageVsSerpAnalyzer();
		$this->schema = new SchemaStorage();
	}

	public function analyze(int $post_id, string $query): array {

		$post = get_post($post_id);
		if (!$post) {
			return [
				'ok' => false,
				'error' => 'post_not_found',
				'post_id' => $post_id,
			];
		}

		$url = (string) get_permalink($post_id);
		$title = (string) get_the_title($post_id);

		$content = (string) $post->post_content;

		$h1 = $this->extract_first_heading($content, 'h1');
		if ($h1 === '') {
			$h1 = $title;
		}

		$h2 = $this->extract_headings($content, 'h2', 40);

		$schema_types = $this->extract_schema_types($this->schema->get($post_id));

		$compare = $this->crawl->compare([
			'post_id' => $post_id,
			'url'     => $url,
			'h1'      => $h1,
			'h2'      => $h2,
			'schema'  => $schema_types,
			'query'   => $query,
		]);

		return [
			'ok' => true,
			'post_id' => $post_id,
			'query' => $query,
			'page' => [
				'url' => $url,
				'title' => $title,
				'h1' => $h1,
				'h2' => $h2,
				'schema_types' => $schema_types,
			],
			'compare' => $compare,
		];
	}

	private function extract_first_heading(string $html, string $tag): string {
		$tag = strtolower($tag);
		if ($tag !== 'h1' && $tag !== 'h2' && $tag !== 'h3' && $tag !== 'h4' && $tag !== 'h5' && $tag !== 'h6') {
			return '';
		}
		if (!preg_match('~<'. $tag .'\b[^>]*>(.*?)</'. $tag .'>~is', $html, $m)) {
			return '';
		}
		return $this->clean_text($m[1]);
	}

	private function extract_headings(string $html, string $tag, int $limit = 30): array {
		$tag = strtolower($tag);
		if ($tag !== 'h1' && $tag !== 'h2' && $tag !== 'h3' && $tag !== 'h4' && $tag !== 'h5' && $tag !== 'h6') {
			return [];
		}

		if (!preg_match_all('~<'. $tag .'\b[^>]*>(.*?)</'. $tag .'>~is', $html, $mm)) {
			return [];
		}

		$out = [];
		foreach ($mm[1] as $raw) {
			$txt = $this->clean_text($raw);
			if ($txt === '') continue;
			$out[] = $txt;
			if (count($out) >= $limit) break;
		}

		// uniq preserving order
		$uniq = [];
		$seen = [];
		foreach ($out as $v) {
			$k = strtolower($v);
			if (isset($seen[$k])) continue;
			$seen[$k] = true;
			$uniq[] = $v;
		}
		return $uniq;
	}

	private function clean_text(string $html_fragment): string {
		$txt = wp_strip_all_tags($html_fragment, true);
		$txt = html_entity_decode($txt, ENT_QUOTES | ENT_HTML5, 'UTF-8');
		$txt = preg_replace('/\s+/u', ' ', (string) $txt);
		$txt = trim((string) $txt);
		return $txt;
	}

	private function extract_schema_types(string $jsonld): array {
		$jsonld = trim($jsonld);
		if ($jsonld === '') return [];

		$decoded = json_decode($jsonld, true);
		if (!is_array($decoded)) return [];

		$types = [];

		$walk = function ($node) use (&$walk, &$types) {
			if (is_array($node)) {
				if (isset($node['@type'])) {
					$t = $node['@type'];
					if (is_string($t) && $t !== '') $types[] = $t;
					if (is_array($t)) {
						foreach ($t as $tt) {
							if (is_string($tt) && $tt !== '') $types[] = $tt;
						}
					}
				}
				foreach ($node as $v) {
					$walk($v);
				}
			}
		};

		$walk($decoded);

		$types = array_values(array_unique(array_map('strval', $types)));
		return $types;
	}
}
