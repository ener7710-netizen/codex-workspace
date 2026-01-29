<?php
declare(strict_types=1);

namespace SEOJusAI\Modules;

use SEOJusAI\Core\Contracts\ModuleInterface;
use SEOJusAI\Core\Kernel;
use SEOJusAI\PageSpeed\PageSpeedAdminActions;

defined('ABSPATH') || exit;

final class PagespeedModule implements ModuleInterface {

	public function get_slug(): string {
		return 'pagespeed';
	}

	public function register(Kernel $kernel): void {
		$kernel->register_module($this->get_slug(), $this);
	}

	public function init(Kernel $kernel): void {
		add_action('init', static function (): void {
			if (is_admin() && class_exists(PageSpeedAdminActions::class)) {
				PageSpeedAdminActions::register();
			}
		}, 20);
	}
}
