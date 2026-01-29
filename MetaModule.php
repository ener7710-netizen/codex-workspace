<?php
declare(strict_types=1);

namespace SEOJusAI\Modules;

use SEOJusAI\Core\Contracts\ModuleInterface;
use SEOJusAI\Core\Kernel;
use SEOJusAI\Meta\MetaRenderer;

defined('ABSPATH') || exit;

final class MetaModule implements ModuleInterface {

	public function get_slug(): string { return 'meta'; }

	public function register(Kernel $kernel): void {
		$kernel->register_module($this->get_slug(), $this);
	}

	public function init(Kernel $kernel): void {
		add_action('init', function () {
			(new MetaRenderer())->register();
		}, 20);

	}
}
