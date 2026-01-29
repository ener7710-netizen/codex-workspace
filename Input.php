<?php
declare(strict_types=1);

namespace SEOJusAI\Input;

defined('ABSPATH') || exit;

final class Input {

	/**
	 * Get a raw value from $_POST without sanitization. Kept for backward compatibility.
	 *
	 * @deprecated Use post_int(), post_text() or post_key() instead for proper sanitization.
	 */
	public static function post(string $key, $default = null) {
		if (!isset($_POST[$key])) {
			return $default;
		}
		return wp_unslash($_POST[$key]);
	}

	public static function get(string $key, $default = null) {
		if (!isset($_GET[$key])) {
			return $default;
		}
		return wp_unslash($_GET[$key]);
	}

	/**
	 * Fetch an integer from $_POST with proper unslashing and casting.
	 *
	 * @param string $key
	 * @param int    $default
	 */
	public static function post_int(string $key, int $default = 0): int {
		return isset($_POST[$key]) ? (int) wp_unslash($_POST[$key]) : $default;
	}

	/**
	 * Fetch a sanitized text field from $_POST.
	 *
	 * @param string $key
	 * @param string $default
	 */
	public static function post_text(string $key, string $default = ''): string {
		if (!isset($_POST[$key])) {
			return $default;
		}
		return sanitize_text_field((string) wp_unslash($_POST[$key]));
	}

	/**
	 * Fetch a sanitized key (slug) from $_POST.
	 *
	 * @param string $key
	 * @param string $default
	 */
	public static function post_key(string $key, string $default = ''): string {
		if (!isset($_POST[$key])) {
			return $default;
		}
		return sanitize_key((string) wp_unslash($_POST[$key]));
	}

	/**
	 * Fetch an integer from $_GET with proper unslashing and casting.
	 *
	 * @param string $key
	 * @param int    $default
	 */
	public static function get_int(string $key, int $default = 0): int {
		return isset($_GET[$key]) ? (int) wp_unslash($_GET[$key]) : $default;
	}

	/**
	 * Fetch a sanitized text field from $_GET.
	 *
	 * @param string $key
	 * @param string $default
	 */
	public static function get_text(string $key, string $default = ''): string {
		if (!isset($_GET[$key])) {
			return $default;
		}
		return sanitize_text_field((string) wp_unslash($_GET[$key]));
	}

	/**
	 * Fetch a sanitized key (slug) from $_GET.
	 *
	 * @param string $key
	 * @param string $default
	 */
	public static function get_key(string $key, string $default = ''): string {
		if (!isset($_GET[$key])) {
			return $default;
		}
		return sanitize_key((string) wp_unslash($_GET[$key]));
	}
}
