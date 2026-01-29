<?php
declare(strict_types=1);

namespace SEOJusAI\Modules;

use SEOJusAI\Core\Kernel;
use SEOJusAI\Core\Contracts\ModuleInterface;
use SEOJusAI\Experiments\ExperimentsFrontend;

defined('ABSPATH') || exit;

final class ExperimentsModule implements ModuleInterface {

	public function get_slug(): string {
		return 'experiments';
	}

	public function register(Kernel $kernel): void {
		$kernel->register_module($this->get_slug(), $this);
	}

	public function init(Kernel $kernel): void {
		ExperimentsFrontend::register();
	}
}
