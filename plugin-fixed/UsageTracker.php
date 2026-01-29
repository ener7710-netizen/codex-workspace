<?php
declare(strict_types=1);

namespace SEOJusAI\AI\Billing;

defined('ABSPATH') || exit;

/**
 * UsageTracker
 *
 * Логирует использование AI (для статистики и SaaS)
 */
final class UsageTracker {

	private const OPTION_KEY = 'seojusai_ai_usage_log';

	public static function log(array $entry): void {

		$log = get_option(self::OPTION_KEY, []);
		$log = is_array($log) ? $log : [];

		$log[] = array_merge(
			[
				'time' => time(),
			],
			$entry
		);

		// ограничиваем размер
		if (count($log) > 200) {
			$log = array_slice($log, -200);
		}

		update_option(self::OPTION_KEY, $log, false);
	}

	public static function all(): array {
		$data = get_option(self::OPTION_KEY, []);
		return is_array($data) ? $data : [];
	}
}
