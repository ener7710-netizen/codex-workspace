<?php
declare(strict_types=1);

namespace SEOJusAI\Admin;

use SEOJusAI\Core\ModuleRegistry;
use SEOJusAI\Input\Input;

defined('ABSPATH') || exit;

final class AjaxModules {

	public function __construct() {
		add_action('wp_ajax_seojusai_toggle_module', [$this, 'toggle']);
	}

	public function toggle(): void {

		if ( ! current_user_can('manage_options') ) {
			wp_send_json_error(['message' => 'forbidden'], 403);
		}

		// ✅ ЄДИНИЙ стандарт для AJAX
		check_ajax_referer('seojusai_toggle_module', '_ajax_nonce');

		$slug    = Input::string(Input::post('module', ''), 60, true);
		$enabled = Input::int(Input::post('enabled', 0), 0, 1) === 1;

		if ($slug === '') {
			wp_send_json_error(['message' => 'invalid_module']);
		}

		$registry = ModuleRegistry::instance();

		if ( ! $registry->set_enabled($slug, $enabled) ) {
			wp_send_json_error(['message' => 'locked_or_failed']);
		}

		wp_send_json_success([
			'module'  => $slug,
			'enabled' => $enabled,
		]);
	}
}
