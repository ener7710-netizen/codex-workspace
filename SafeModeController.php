<?php
declare(strict_types=1);

namespace SEOJusAI\Safety;

use SEOJusAI\Capabilities\CapabilityGuard;
use SEOJusAI\Capabilities\CapabilityMap;

defined('ABSPATH') || exit;

final class SafeModeController {

	public const ACTION_TOGGLE = 'seojusai_toggle_safe_mode';

	public function register(): void {
		add_action('admin_post_' . self::ACTION_TOGGLE, [$this, 'handle_toggle']);
	}

	public function handle_toggle(): void {
		if (!is_admin()) {
			wp_die(esc_html__('Недоступно.', 'seojusai'), 403);
		}

		if (!CapabilityGuard::can(CapabilityMap::MANAGE_SETTINGS)) {
			wp_die(esc_html__('Недостатньо прав.', 'seojusai'), 403);
		}

		check_admin_referer(self::ACTION_TOGGLE);

		$enable = isset($_POST['enable']) ? (bool) (int) wp_unslash($_POST['enable']) : false;
		$reason = isset($_POST['reason']) ? sanitize_text_field((string) wp_unslash($_POST['reason'])) : 'manual';

		if ($enable) {
			SafeMode::activate($reason !== '' ? $reason : 'manual');
		} else {
			SafeMode::deactivate();
		}

		$redirect = wp_get_referer();
		if (!is_string($redirect) || $redirect === '') {
			$redirect = admin_url('admin.php?page=seojusai-governance');
		}

		wp_safe_redirect($redirect);
		exit;
	}
}
