<?php
declare(strict_types=1);

namespace SEOJusAI\AI\Billing;

defined('ABSPATH') || exit;

/**
 * CreditManager
 *
 * ЄДИНИЙ менеджер AI-лімітів
 */
final class CreditManager {

	private const OPTION_KEY = 'seojusai_ai_credits';

	public static function get_balance(int $user_id = 0): int {

		$data = get_option(self::OPTION_KEY, []);
		$data = is_array($data) ? $data : [];

		$key = $user_id > 0 ? (string) $user_id : 'global';

		return max(0, (int) ($data[$key] ?? 0));
	}

	public static function has_credits(int $amount = 1, int $user_id = 0): bool {
		return self::get_balance($user_id) >= $amount;
	}

	public static function consume(int $amount = 1, int $user_id = 0): bool {

		$data = get_option(self::OPTION_KEY, []);
		$data = is_array($data) ? $data : [];

		$key     = $user_id > 0 ? (string) $user_id : 'global';
		$current = max(0, (int) ($data[$key] ?? 0));

		if ($current < $amount) {
			return false;
		}

		$data[$key] = $current - $amount;
		update_option(self::OPTION_KEY, $data, false);

		return true;
	}

	public static function add(int $amount, int $user_id = 0): void {

		if ($amount <= 0) {
			return;
		}

		$data = get_option(self::OPTION_KEY, []);
		$data = is_array($data) ? $data : [];

		$key = $user_id > 0 ? (string) $user_id : 'global';
		$data[$key] = max(0, (int) ($data[$key] ?? 0)) + $amount;

		update_option(self::OPTION_KEY, $data, false);
	}
}
