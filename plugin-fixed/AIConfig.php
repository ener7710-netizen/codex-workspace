<?php
declare(strict_types=1);

namespace SEOJusAI\AI;

use SEOJusAI\Security\SecretsVault;

defined('ABSPATH') || exit;

final class AIConfig {

	private const OPTION_KEY = 'seojusai_ai';

	public static function get(): array {
		$opt = get_option(self::OPTION_KEY, []);
		return is_array($opt) ? $opt : [];
	}

	public static function get_openai_key(): string {
		$vault = new SecretsVault();
		$key = $vault->get('openai_key');
		if ($key !== '') { return $key; }
		$opt = self::get();
		return (string) ($opt['openai_key'] ?? '');
	}

	public static function get_gemini_key(): string {
		$vault = new SecretsVault();
		$key = $vault->get('gemini_key');
		if ($key !== '') { return $key; }
		$opt = self::get();
		return (string) ($opt['gemini_key'] ?? '');
	}

	public static function proxy_enabled(): bool {
		$opt = self::get();
		return !empty($opt['proxy_enabled']);
	}

	public static function proxy_url(): string {
		$opt = self::get();
		return (string) ($opt['proxy_url'] ?? '');
	}

	public static function limits_rpm(): int {
		$opt = self::get();
		$v = (int) ($opt['limits_rpm'] ?? 30);
		return $v > 0 ? $v : 30;
	}

	public static function save(array $data): void {
		$vault = new SecretsVault();
		if (isset($data['openai_key']) && is_string($data['openai_key']) && $data['openai_key'] !== '') {
			$vault->set('openai_key', $data['openai_key']);
			unset($data['openai_key']);
		}
		if (isset($data['gemini_key']) && is_string($data['gemini_key']) && $data['gemini_key'] !== '') {
			$vault->set('gemini_key', $data['gemini_key']);
			unset($data['gemini_key']);
		}
		update_option(self::OPTION_KEY, $data, false);
	}
}
