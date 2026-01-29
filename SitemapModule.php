<?php
declare(strict_types=1);

namespace SEOJusAI\Modules;

use SEOJusAI\Compat\SeoEnvironmentDetector;
use SEOJusAI\Core\Contracts\ModuleInterface;
use SEOJusAI\Core\Kernel;
use SEOJusAI\Sitemap\SitemapController;

defined('ABSPATH') || exit;

final class SitemapModule implements ModuleInterface {

	public function get_slug(): string { return 'sitemap'; }

	public function register(Kernel $kernel): void {
		$kernel->register_module($this->get_slug(), $this);
	}

	public function init(Kernel $kernel): void {
		if (SeoEnvironmentDetector::should_disable_frontend_emitting()) {
			return;
		}

		add_action('init', static function () {
			(new SitemapController())->register();
		}, 20);
	}
}
