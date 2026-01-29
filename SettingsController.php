<?php
declare(strict_types=1);

namespace SEOJusAI\Rest\Controllers;

use WP_REST_Request;
use WP_REST_Response;
use SEOJusAI\Input\Input;
use SEOJusAI\Core\EmergencyStop;
use SEOJusAI\Security\SecretsVault;
use SEOJusAI\Rest\RestKernel;
use SEOJusAI\Rest\AbstractRestController;
use SEOJusAI\Rest\Contracts\RestControllerInterface;

defined('ABSPATH') || exit;

final class SettingsController extends AbstractRestController implements RestControllerInterface {

	private const OPTION_KEY = 'seojusai_settings';

	public function register_routes(): void {
		register_rest_route('seojusai/v1', '/settings', [
			'methods'             => 'GET',
			'permission_callback' => [ RestKernel::class, 'can_manage' ],
			'callback'            => [ $this, 'get' ],
		]);

		register_rest_route('seojusai/v1', '/settings', [
			'methods'             => 'POST',
			'permission_callback' => [ RestKernel::class, 'can_manage' ],
			'callback'            => [ $this, 'save' ],
		]);
	}

	public function get(): WP_REST_Response {
		$s = get_option(self::OPTION_KEY, []);
		$vault = new SecretsVault();

		return $this->ok([
			'openai' => [
				'key_set' => $vault->has('openai_key'),
				'model'   => $s['openai']['model'] ?? 'gpt-4o',
			],
			'gemini' => [
				'key_set' => $vault->has('gemini_key'),
				'model'   => $s['gemini']['model'] ?? 'gemini-1.5-pro',
			],
			'serp'   => [
				'google_api_key_set' => $vault->has('serp_google_api_key'),
				'google_cx'          => $s['serp']['google_cx'] ?? '',
				'gsc_json_set'       => $vault->has('gsc_json'),
			],
		]);
	}

	public function save(WP_REST_Request $request): WP_REST_Response {
		if (EmergencyStop::is_active()) return $this->error('Emergency', 'stop', 423);

		$__raw = (string) $request->get_body();
		$__parsed = Input::json_array_strict($__raw, 200000);
		if (!$__parsed['ok']) return $this->error('Invalid JSON payload', (string)$__parsed['error'], $__parsed['error']==='payload_too_large' ? 413 : 400);
		$params = (array) $__parsed['data'];

		$option = get_option(self::OPTION_KEY, []);

		// --- OpenAI / Gemini secrets ---
		$vault = new SecretsVault();

		if (isset($params['openai']) && is_array($params['openai'])) {
			$model = Input::string($params['openai']['model'] ?? '', 64, false);
			if ($model !== '') {
				$option['openai']['model'] = $model;
			}
			$key = isset($params['openai']['key']) ? (string) $params['openai']['key'] : '';
			if ($key !== '') {
				$vault->set('openai_key', $key);
			}
		}

		if (isset($params['gemini']) && is_array($params['gemini'])) {
			$model = Input::string($params['gemini']['model'] ?? '', 64, false);
			if ($model !== '') {
				$option['gemini']['model'] = $model;
			}
			$key = isset($params['gemini']['key']) ? (string) $params['gemini']['key'] : '';
			if ($key !== '') {
				$vault->set('gemini_key', $key);
			}
		}

		// --- SERP / GSC credentials ---
		if (isset($params['serp']) && is_array($params['serp'])) {
			$option['serp'] = [
				'google_cx' => Input::string($params['serp']['google_cx'] ?? '', 128, false),
			];

			$serp_key = Input::string($params['serp']['google_api_key'] ?? '', 128, false);
			if ($serp_key !== '') {
				$vault->set('serp_google_api_key', $serp_key);
			}


			// gsc_json: strict JSON, size limit
			$gsc_json_raw = isset($params['serp']['gsc_json']) ? (string) $params['serp']['gsc_json'] : '';
			if ($gsc_json_raw !== '') {
				if (strlen($gsc_json_raw) > 200000) { // ~200KB
					return $this->error('GSC JSON too large', 'payload_too_large', 413);
				}
				$decoded = json_decode($gsc_json_raw, true);
				if (!is_array($decoded)) {
					return $this->error('Invalid GSC JSON', 'invalid_json', 422);
				}
				// store normalized json in secrets vault (encrypted)
				$vault->set('gsc_json', wp_json_encode($decoded, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
			}
		}

		update_option(self::OPTION_KEY, $option);
		do_action('seojusai/settings/updated', $option);

		return $this->ok(['success' => true]);
	}
}
