<?php
declare(strict_types=1);

namespace SEOJusAI\AI\Proxy;

defined('ABSPATH') || exit;

final class ProxyResolver {

	public static function is_proxy_enabled(): bool {
		return (bool) get_option('seojusai_use_proxy', false);
	}

	public static function endpoint(): string {

		$url = (string) get_option('seojusai_proxy_url');

		if ($url !== '') {
			return rtrim($url, '/');
		}

		// fallback (future SaaS)
		return '';
	}

	public static function headers(): array {

		$key = (string) get_option('seojusai_proxy_key');

		return $key !== ''
			? ['X-SEOJusAI-Key' => $key]
			: [];
	}
}
