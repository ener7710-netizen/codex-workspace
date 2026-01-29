<?php
declare(strict_types=1);

namespace SEOJusAI\Modules;

use SEOJusAI\Compat\SeoEnvironmentDetector;
use SEOJusAI\Core\Contracts\ModuleInterface;
use SEOJusAI\Core\Kernel;
use SEOJusAI\Robots\RobotsTxt;

defined('ABSPATH') || exit;

final class RobotsModule implements ModuleInterface {

	public function get_slug(): string { return 'robots'; }

	public function register(Kernel $kernel): void {
		$kernel->register_module($this->get_slug(), $this);
	}

	public function init(Kernel $kernel): void {
		// Уникаємо дублювання з Yoast/RankMath/AIOSEO/SEOPress
		if (SeoEnvironmentDetector::should_disable_frontend_emitting()) {
			return;
		}
		add_action('init', static function () {
			(new RobotsTxt())->register();
		}, 20);
	}
}
