<?php
declare(strict_types=1);

namespace SEOJusAI\Robots;

use SEOJusAI\Compat\SeoEnvironmentDetector;

defined('ABSPATH') || exit;

final class RobotsTxt {

	public function register(): void {
		add_filter('robots_txt', [$this, 'filter'], 20, 2);
	}

	public function filter(string $output, bool $public): string {
		// Якщо інший SEO-плагін керує robots/sitemap — не втручаємось
		if (SeoEnvironmentDetector::is_any_seo_active()) { return $output; }

		$enabled = (string) get_option('seojusai_robots_append_sitemap', '1');
		if ($enabled !== '1') { return $output; }

		$sitemap = home_url('/wp-sitemap.xml');
		if (stripos($output, 'Sitemap:') === false) {
			$output = rtrim($output) . "\nSitemap: {$sitemap}\n";
		}
		return $output;
	}
}
