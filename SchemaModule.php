<?php
declare(strict_types=1);

namespace SEOJusAI\Modules;

use SEOJusAI\Core\Contracts\ModuleInterface;
use SEOJusAI\Core\Kernel;
use SEOJusAI\Schema\SchemaRenderer;

defined('ABSPATH') || exit;

final class SchemaModule implements ModuleInterface {

	public function get_slug(): string {
		return 'schema';
	}

	public function register(Kernel $kernel): void {
		$kernel->register_module($this->get_slug(), $this);
	}

	public function init(Kernel $kernel): void {
		if (class_exists(SchemaRenderer::class)) {
			(new SchemaRenderer())->register();
		}
	}
}
