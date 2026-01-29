<?php
declare(strict_types=1);

namespace SEOJusAI\Core;

defined('ABSPATH') || exit;

/**
 * Safe translation wrapper.
 *
 * WP 6.7+ logs a notice when translations for a domain are triggered too early.
 * We must avoid calling __() / _e() before init in places like cron_schedules or early registries.
 */
final class I18n {

	public static function t(string $text, string $domain = 'seojusai'): string {
		if (\function_exists('__') && \did_action('init')) {
			return __( $text, $domain );
		}
		return $text;
	}

	public static function et(string $text, string $domain = 'seojusai'): void {
		echo esc_html( self::t($text, $domain) );
	}
}
