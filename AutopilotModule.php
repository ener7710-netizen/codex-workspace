<?php
declare(strict_types=1);

namespace SEOJusAI\Modules;

use SEOJusAI\Core\Contracts\ModuleInterface;
use SEOJusAI\Core\Kernel;
use SEOJusAI\Autopilot\AutopilotEngine;
use SEOJusAI\Autopilot\PageActionsAutopilotRunner;
use SEOJusAI\Autopilot\PageActionsStrategistRunner;

defined('ABSPATH') || exit;

final class AutopilotModule implements ModuleInterface {

	public function get_slug(): string {
		return 'autopilot';
	}

	public function register(Kernel $kernel): void {
		$kernel->register_module($this->get_slug(), $this);
	}

	public function init(Kernel $kernel): void {
		if (class_exists(AutopilotEngine::class)) {
			(new AutopilotEngine())->register();
		}

		// ✅ Page-level AI actions → Autopilot review tasks (read-only planning).
		if (class_exists(PageActionsAutopilotRunner::class)) {
			(new PageActionsAutopilotRunner())->register();
		}

		// ✅ Strategist runner: applies allowlist page actions automatically (no UI buttons).
		if (class_exists(PageActionsStrategistRunner::class)) {
			(new PageActionsStrategistRunner())->register();
		}
	}
}
