<?php
declare(strict_types=1);

namespace SEOJusAI\Modules;

use SEOJusAI\Breadcrumbs\Breadcrumbs;
use SEOJusAI\Core\Contracts\ModuleInterface;
use SEOJusAI\Core\Kernel;

defined('ABSPATH') || exit;

final class BreadcrumbsModule implements ModuleInterface {

	public function get_slug(): string {
		return 'breadcrumbs';
	}

	public function register(Kernel $kernel): void {
		$kernel->register_module($this->get_slug(), $this);
	}

	public function init(Kernel $kernel): void {
		add_action(
			'init',
			static function (): void {
				(new Breadcrumbs())->register();
			},
			5
		);
	}
}
