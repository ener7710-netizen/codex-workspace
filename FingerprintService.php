<?php
declare(strict_types=1);

namespace SEOJusAI\SERP;

use SEOJusAI\Core\EmergencyStop;

defined('ABSPATH') || exit;

final class FingerprintService {

	public function build_for_urls(array $urls): array {
		if ( EmergencyStop::is_active() ) return [];

		$urls = array_values(array_filter(array_map('esc_url_raw', $urls)));
		$out = [];

		foreach ( array_slice($urls, 0, 10) as $url ) {
			$out[] = $this->fingerprint_one($url);
		}

		return $out;
	}

	private function fingerprint_one(string $url): array {
		$response = wp_remote_get($url, [
			'timeout'    => 15,
			'user-agent' => 'SEOJusAI/1.0 (+WordPress; Lawyer SEO Engine)',
		]);

		if ( is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200 ) {
			return ['url' => $url, 'error' => true];
		}

		$body = wp_remote_retrieve_body($response);

		return [
			'url'          => $url,
			'title'        => $this->extract_single_tag($body, 'title'),
			'h1'           => $this->extract_single_tag($body, 'h1'),
			'structure'    => $this->extract_structure($body), // Глибока структура H2-H4
			'schema_types' => $this->extract_schema_types($body),
		];
	}

	private function extract_single_tag(string $html, string $tag): string {
		if ( preg_match("~<{$tag}[^>]*>(.*?)</{$tag}>~is", $html, $m) ) {
			return trim(wp_strip_all_tags(html_entity_decode($m[1], ENT_QUOTES | ENT_HTML5)));
		}
		return '';
	}

	private function extract_structure(string $html): array {
		$results = [];
		// Збираємо заголовки 2, 3 та 4 рівнів
		if ( preg_match_all('~<(h[2-4])[^>]*>(.*?)</\1>~is', $html, $m) ) {
			foreach ( $m[1] as $index => $tag ) {
				$text = trim(wp_strip_all_tags(html_entity_decode($m[2][$index], ENT_QUOTES | ENT_HTML5)));
				if ( strlen($text) > 3 ) { // Відсікаємо занадто короткі технічні заголовки
					$results[] = [
						'level' => $tag,
						'text'  => $text
					];
				}
			}
		}
		return $results;
	}

	// extract_schema_types залишається без змін (з вашої версії)
}
