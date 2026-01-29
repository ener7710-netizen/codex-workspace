<?php
declare(strict_types=1);

namespace SEOJusAI\Analyze\Intent;

defined('ABSPATH') || exit;

/**
 * UrlClusterer
 * Групує URL у кластери за шляхом (перші 1-2 сегменти) + нормалізацією.
 */
final class UrlClusterer {

	/**
	 * @param string[] $urls
	 * @return array<string,string[]> clusters keyed by cluster_id
	 */
	public function cluster(array $urls): array {
		$clusters = [];
		foreach ($urls as $url) {
			$cid = $this->cluster_id($url);
			$clusters[$cid] ??= [];
			$clusters[$cid][] = $url;
		}
		return $clusters;
	}

	public function cluster_id(string $url): string {
		$path = (string) wp_parse_url($url, PHP_URL_PATH);
		$path = trim($path ?? '', '/');
		if ($path === '') { return 'home'; }

		$parts = array_values(array_filter(explode('/', $path)));
		$head = array_slice($parts, 0, 2);
		return implode('/', $head);
	}
}
