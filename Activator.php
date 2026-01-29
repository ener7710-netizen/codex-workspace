<?php
declare(strict_types=1);

namespace SEOJusAI\Core;

use SEOJusAI\AI\Billing\CreditManager;
use SEOJusAI\Features\FeatureResolver;

defined('ABSPATH') || exit;

final class Activator {

	public static function activate(): void {

		// Feature Flags defaults
		if (class_exists(FeatureResolver::class)) {
			FeatureResolver::ensure_defaults();
		}


		// GLOBAL credits (для всего сайта)
		if (CreditManager::get_balance(0) <= 0) {
			CreditManager::add(50, 0);
		}

		// Sitemap/Redirects rewrite
		if (function_exists('flush_rewrite_rules')) {
			flush_rewrite_rules(false);
		}
	}
}
