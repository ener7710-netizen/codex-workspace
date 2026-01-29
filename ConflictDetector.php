<?php
declare(strict_types=1);

namespace SEOJusAI\Governance;

use SEOJusAI\Capabilities\CapabilityGuard;
use SEOJusAI\Capabilities\CapabilityMap;

defined('ABSPATH') || exit;

final class ConflictDetector {

	public const OPTION_KEY = 'seojusai_conflict_state';

	/**
	 * Detect active SEO plugins that may overlap with SEOJusAI zones.
	 *
	 * @return array{controller:string,active:array{rank_math:bool,yoast:bool,aioseo:bool},ts:int}
	 */
	public static function detect(): array {
		// Ensure plugin functions exist.
		if (!function_exists('is_plugin_active')) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$active = [
			'rank_math' => defined('RANK_MATH_VERSION') || class_exists('RankMath\\Admin') || is_plugin_active('seo-by-rank-math/rank-math.php'),
			'yoast'     => defined('WPSEO_VERSION') || class_exists('WPSEO_Frontend') || is_plugin_active('wordpress-seo/wp-seo.php'),
			'aioseo'    => defined('AIOSEO_VERSION') || class_exists('AIOSEO\\Plugin') || is_plugin_active('all-in-one-seo-pack/all_in_one_seo_pack.php') || is_plugin_active('all-in-one-seo-pack-pro/all_in_one_seo_pack.php'),
		];

		$controller = '—';
		if ($active['rank_math']) {
			$controller = 'Rank Math';
		} elseif ($active['yoast']) {
			$controller = 'Yoast SEO';
		} elseif ($active['aioseo']) {
			$controller = 'AIOSEO';
		}

		return [
			'controller' => $controller,
			'active'     => $active,
			'ts'         => time(),
		];
	}

	public static function store_state(): void {
		update_option(self::OPTION_KEY, self::detect(), false);
	}

	/**
	 * @return array{controller:string,active:array{rank_math:bool,yoast:bool,aioseo:bool},ts:int}
	 */
	public static function state(): array {
		$state = get_option(self::OPTION_KEY, null);
		if (!is_array($state)) {
			$state = self::detect();
			update_option(self::OPTION_KEY, $state, false);
		}
		$state['controller'] = isset($state['controller']) ? (string) $state['controller'] : '—';
		$state['active'] = isset($state['active']) && is_array($state['active']) ? $state['active'] : ['rank_math'=>false,'yoast'=>false,'aioseo'=>false];
		$state['ts'] = isset($state['ts']) ? (int) $state['ts'] : 0;
		return $state;
	}

	public static function register(): void {
		add_action('admin_init', [self::class, 'store_state'], 5);

		add_action('admin_notices', static function (): void {
			if (!is_admin() || !CapabilityGuard::can(CapabilityMap::MANAGE_SETTINGS)) {
				return;
			}
			$state = self::state();
			$controller = (string) ($state['controller'] ?? '—');
			if ($controller === '—') {
				return;
			}
			echo '<div class="notice notice-warning"><p>';
			echo esc_html(sprintf(
				/* translators: %s: plugin name */
				__('SEOJusAI: виявлено активний SEO‑плагін (%s). Уникайте дублювання Schema/Sitemap/Redirects або увімкніть Safe Mode.', 'seojusai'),
				$controller
			));
			echo '</p></div>';
		}, 11);
	}
}
