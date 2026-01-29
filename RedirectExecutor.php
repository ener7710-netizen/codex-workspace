<?php
declare(strict_types=1);

namespace SEOJusAI\Redirects;

use SEOJusAI\Safety\SafeMode;
use SEOJusAI\Capabilities\CapabilityGuard;
use SEOJusAI\Capabilities\CapabilityMap;

defined('ABSPATH') || exit;

final class RedirectExecutor {

	public function register(): void {
		add_action('template_redirect', [$this, 'maybe_redirect'], 0);
		add_action('template_redirect', [$this, 'maybe_log_404'], 999);
	}

	public function maybe_redirect(): void {
		if (class_exists(SafeMode::class) && SafeMode::is_enabled()) {
			return;
		}

		if (is_admin()) return;

		$repo = new RedirectRepository();
		if (!$repo->exists()) return;

		$req_uri = (string) ($_SERVER['REQUEST_URI'] ?? '');
		$req_uri = $req_uri ?: '/';
		$row = $repo->match($req_uri);
		if (!$row) return;

		$repo->bump_hit((int)$row['id']);
		$to = (string) ($row['to_url'] ?? '');
		$code = (int) ($row['code'] ?? 301);
		if ($to === '') return;

		$to_valid = wp_validate_redirect($to);
		if (!$to_valid) { return; }

		// Додаткове обмеження: дозволяємо тільки той самий хост
		$home_host = wp_parse_url(home_url('/'), PHP_URL_HOST);
		$to_host   = wp_parse_url($to_valid, PHP_URL_HOST);
		if ($to_host && $home_host && strcasecmp((string)$to_host, (string)$home_host) !== 0) {
			return;
		}

		wp_safe_redirect($to_valid, $code);
		exit;
	}

	public function maybe_log_404(): void {
		if (is_admin()) return;
		if (!is_404()) return;

		$logger = new NotFoundLogger();
		if (!$logger->exists()) return;

		$url = home_url((string) ($_SERVER['REQUEST_URI'] ?? '/'));
		$ref = (string) ($_SERVER['HTTP_REFERER'] ?? '');
		$logger->log($url, $ref);
	}
}
