<?php
declare(strict_types=1);

namespace SEOJusAI\Modules;

use SEOJusAI\Core\Contracts\ModuleInterface;
use SEOJusAI\Core\Kernel;
use SEOJusAI\Redirects\RedirectExecutor;
use SEOJusAI\Utils\Logger;

defined('ABSPATH') || exit;

final class RedirectsModule implements ModuleInterface {

	public function get_slug(): string { return 'redirects'; }

	public function register(Kernel $kernel): void {
		$kernel->register_module($this->get_slug(), $this);
	}

	public function init(Kernel $kernel): void {
		$register = static function (): void {
			try {
				(new RedirectExecutor())->register();
			} catch (\Throwable $e) {
				if (defined('WP_DEBUG') && WP_DEBUG) {
					if (class_exists(Logger::class)) {
			Logger::error('redirects_module_error', ['message' => '[SEOJusAI Redirects] ' . $e->getMessage()]);
		}
				}
			}
		};

		// Primary hook.
		add_action('init', $register, 5);
		// Fallback: in case init is bypassed by early exit or aggressive caching.
		add_action('wp_loaded', $register, 5);
	}
}
