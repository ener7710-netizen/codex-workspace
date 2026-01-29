<?php
declare(strict_types=1);

namespace SEOJusAI\Rest;

use SEOJusAI\Core\Plugin;
use WP_REST_Response;

defined('ABSPATH') || exit;

/**
 * AbstractRestController
 *
 * ЄДИНА база для всіх REST-контролерів.
 */
abstract class AbstractRestController {

	protected Plugin $plugin;

	public function __construct() {
		$this->plugin = Plugin::instance();
	}

	protected function ok(array $data = [], int $status = 200): WP_REST_Response {
		return new WP_REST_Response([
			'success' => true,
			'data'    => $data,
		], $status);
	}

	protected function error(string $message, string $code, int $status): WP_REST_Response {
		return new WP_REST_Response([
			'success' => false,
			'error'   => $message,
			'code'    => $code,
		], $status);
	}
}
