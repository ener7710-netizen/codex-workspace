<?php
declare(strict_types=1);

namespace SEOJusAI\Governance;

use SEOJusAI\GSC\GscServiceAccount;

defined('ABSPATH') || exit;

/**
 * RealityBoundary
 *
 * Визначає, чи система має доступ до хоча б одного «джерела реальності»:
 * - SERP (провайдер/ключ)
 * - Gemini (ключ)
 * - Google Search Console (Service Account)
 *
 * Використовується як запобіжник для критичних змін.
 */
final class RealityBoundary {

	/**
	 * REST guard (fail-closed).
	 *
	 * Повертає помилку для критичних (мутаційних) REST-запитів, якщо відсутнє
	 * будь-яке «джерело реальності» (SERP/Gemini/GSC).
	 *
	 * @param mixed $result
	 * @param mixed $server
	 * @param mixed $request
	 * @return mixed
	 */
	public static function guard($result, $server, $request) {
		// Якщо результат уже сформований попередніми фільтрами — не чіпаємо.
		if ($result !== null) {
			return $result;
		}

		// Працюємо лише з нашим namespace.
		$route = is_object($request) && method_exists($request, 'get_route') ? (string) $request->get_route() : '';
		if ($route === '' || strpos($route, '/seojusai/v1') !== 0) {
			return $result;
		}

		$method = is_object($request) && method_exists($request, 'get_method') ? strtoupper((string) $request->get_method()) : 'GET';
		$mutating = !in_array($method, ['GET', 'HEAD', 'OPTIONS'], true);
		if (!$mutating) {
			return $result;
		}

		// Якщо немає «реальності» — блокуємо.
		if (!self::can_apply()) {
			return new \WP_REST_Response([
				'success' => false,
				'error'   => __('Межа реальності: відсутні ключі/підключення (SERP/Gemini/GSC). Дії заблоковано.', 'seojusai'),
				'code'    => 'seojusai_reality_boundary_missing',
			], 403);
		}

		return $result;
	}

	/**
	 * Повертає агрегований статус джерел реальності.
	 *
	 * @return array{has_serp:bool,has_gemini:bool,has_gsc:bool,status:string,message:string}
	 */
	public static function status(): array {

		$has_serp = false;
		$has_gemini = false;
		$has_gsc = false;

		// SERP: ключ (SerpAPI/DataForSEO тощо)
		$settings = get_option('seojusai_settings', []);
		$serp = is_array($settings) ? ($settings['serp'] ?? []) : [];
		$serp_key = '';
		if (is_array($serp)) {
			$serp_key = (string) ($serp['serpapi_key'] ?? ($serp['dataforseo_login'] ?? ''));
		}
		if (trim($serp_key) !== '') {
			$has_serp = true;
		}

		// Gemini: ключ з опцій або через фільтр
		$gemini_key = (string) apply_filters('seojusai/gemini_key', (string) get_option('seojusai_gemini_key', ''));
		if (trim($gemini_key) !== '') {
			$has_gemini = true;
		}

		// GSC: service account
		if (class_exists(GscServiceAccount::class) && GscServiceAccount::is_connected()) {
			$has_gsc = true;
		}

		$status = ($has_serp || $has_gemini || $has_gsc) ? 'ok' : 'missing';

		if ($status === 'missing') {
			$message = __('Межа реальності: відсутній', 'seojusai');
		} else {
			$parts = [];
			if ($has_serp) {
				$parts[] = __('SERP', 'seojusai');
			}
			if ($has_gemini) {
				$parts[] = __('Gemini', 'seojusai');
			}
			if ($has_gsc) {
				$parts[] = __('Google', 'seojusai');
			}
			$message = sprintf(
				/* translators: %s: sources list */
				__('Межа реальності: %s', 'seojusai'),
				implode(', ', $parts)
			);
		}

		return [
			'has_serp'   => $has_serp,
			'has_gemini' => $has_gemini,
			'has_gsc'    => $has_gsc,
			'status'     => $status,
			'message'    => $message,
		];
	}

	public static function can_apply(): bool {

		$has_serp   = (bool) apply_filters('seojusai/reality/has_serp', false);
		$has_gemini = (bool) apply_filters('seojusai/reality/has_gemini', false);
		$has_gsc    = (bool) apply_filters('seojusai/reality/has_gsc', false);

		return $has_serp || $has_gemini || $has_gsc;
	}
}
