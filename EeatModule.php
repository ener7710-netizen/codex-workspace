<?php
declare(strict_types=1);

namespace SEOJusAI\Modules;

use SEOJusAI\Core\Contracts\ModuleInterface;
use SEOJusAI\Core\Kernel;
use SEOJusAI\Eeat\EeatMetabox;

defined('ABSPATH') || exit;

final class EeatModule implements ModuleInterface {

	public function get_slug(): string {
		return 'eeat';
	}

	public function register(Kernel $kernel): void {
		$kernel->register_module($this->get_slug(), $this);
	}

	public function init(Kernel $kernel): void {
		add_action('init', static function (): void {
			if (!is_admin()) {
				return;
			}

			if (!class_exists(EeatMetabox::class)) {
				return;
			}

			$metabox = new EeatMetabox();
			$metabox->register();
		}, 20);
	}
}
