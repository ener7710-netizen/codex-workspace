<?php
declare(strict_types=1);

namespace SEOJusAI\SERP;

defined('ABSPATH') || exit;

final class SerpConfig {

	public static function get(): array {

		$o = get_option('seojusai_settings', []);
		$s = $o['serp'] ?? [];

		return [
			'serpapi_key' => $s['serpapi_key'] ?? '',
		];
	}
}
