<?php
declare(strict_types=1);

namespace SEOJusAI\Modules;

use SEOJusAI\Core\Kernel;
use SEOJusAI\Core\Contracts\ModuleInterface;
use SEOJusAI\Core\ModuleRegistry;

defined('ABSPATH') || exit;

final class LeadFunnelModule implements ModuleInterface {

	public function get_slug(): string {
		return 'lead_funnel';
	}

	public function register(Kernel $kernel): void {
		$kernel->register_module($this->get_slug(), $this);
	}

	public function init(Kernel $kernel): void {
		// No heavy init. UI + REST are handled by existing kernels.
		// Keep module lightweight.
	}
}
