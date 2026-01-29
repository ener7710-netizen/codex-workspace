<?php
declare(strict_types=1);

namespace SEOJusAI\AI\Billing;

defined('ABSPATH') || exit;

/**
 * Meter
 * Мінімальний облік витрат: кількість запитів по провайдерах.
 */
final class Meter {

	private const OPTION_KEY = 'seojusai_billing';

	public static function inc(string $provider, int $n = 1): void {
		$provider = sanitize_key($provider);
		if ($provider === '') $provider = 'unknown';

		$opt = get_option(self::OPTION_KEY, []);
		$opt = is_array($opt) ? $opt : [];

		$opt[$provider] = (int) ($opt[$provider] ?? 0) + max(1, $n);

		update_option(self::OPTION_KEY, $opt, false);
	}

	public static function get_all(): array {
		$opt = get_option(self::OPTION_KEY, []);
		return is_array($opt) ? $opt : [];
	}
}
