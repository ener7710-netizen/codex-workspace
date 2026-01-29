<?php
declare(strict_types=1);

namespace SEOJusAI\Rest;

use SEOJusAI\Core\Plugin;
use SEOJusAI\Capabilities\CapabilityGuard;
use SEOJusAI\Capabilities\CapabilityMap;
use SEOJusAI\Security\RateLimiter;
use SEOJusAI\Governance\RealityBoundary;
use SEOJusAI\Rest\Contracts\RestControllerInterface;
use SEOJusAI\Utils\Logger;

defined('ABSPATH') || exit;

/**
 * RestKernel
 *
 * ЕДИНСТВЕННАЯ точка регистрации REST-контроллеров.
 * Поведение = Rank Math style.
 */
final class RestKernel {


	private Plugin $plugin;

	/**
	 * ⚠️ СТРОГО:
	 * - порядок важен
	 * - PageAudit и Chat — В КОНЦЕ
	 *
	 * @var array<class-string<RestControllerInterface>>
	 */
	private array $controllers = [

		// ⚙️ System / Health
		Controllers\HealthController::class,
		Controllers\EmergencyController::class,
		Controllers\SafeModeController::class,
		Controllers\ApprovalController::class,

		// ⚙️ Settings / Modules
		Controllers\ModulesController::class,
		Controllers\SettingsController::class,
		Controllers\ConversionController::class,
		Controllers\FeatureFlagsController::class,
		Controllers\BudgetController::class,

		// 🔍 Data / Sources
		Controllers\GscController::class,
		Controllers\Ga4Controller::class,
		Controllers\AnalyticsController::class,
		Controllers\PageActionsController::class,
		Controllers\SerpController::class,
		Controllers\SerpOverlayController::class,
		Controllers\MarketController::class,
		Controllers\LinkingController::class,
		Controllers\CalibrationController::class,

		// 🔁 Ops / Rollback
		Controllers\RollbackController::class,
		Controllers\UndoController::class,
		Controllers\CleanupController::class,

		// 📊 Analysis / Explain
		Controllers\ExplainController::class,
		Controllers\PageCompareController::class,
		Controllers\PageAnalysisController::class,
		Controllers\PageAuditSummaryController::class,

		// 🤖 AUTOPILOT
		Controllers\AutopilotController::class,
		Controllers\ApplyController::class,
Controllers\SchemaController::class,

		// 🧠 REVIEW
		Controllers\LeadFunnelController::class,
		Controllers\ExperimentsController::class,

		// 🧠 REVIEW
		Controllers\ReviewController::class,

		// 🔥 КРИТИЧЕСКИЕ (ВСЕГДА ПОСЛЕДНИЕ)
		Controllers\PageAuditController::class,
		Controllers\ChatController::class,
	];

	public function __construct(Plugin $plugin) {
		$this->plugin = $plugin;
	}

	/**
	 * Регистрация ядра
	 */
	public function register(): void {
		add_action('rest_api_init', [$this, 'init_controllers'], 1);
		// @boundary Execution is controlled exclusively by AutopilotExecutionLoop.
		// UI/REST/Admin layers must never trigger execution or analysis directly.
		// ❌ Endpoint converted to read-only (execution removed): block all mutating requests.
		add_filter('rest_pre_dispatch', [self::class, 'guard_readonly_namespace'], 0, 3);
		add_filter('rest_pre_dispatch', [self::class, 'guard_payload'], 1, 3);
		add_filter('rest_pre_dispatch', [ RealityBoundary::class, 'guard' ], 2, 3);
	}

	/**
	 * Guard: force SEOJusAI REST namespace to read-only.
	 *
	 * @boundary Execution is controlled exclusively by AutopilotExecutionLoop.
	 * ❌ Any non-GET request is blocked (fail-closed).
	 */
	public static function guard_readonly_namespace($result, $server, $request) {
		$route = is_object($request) && method_exists($request, 'get_route') ? (string) $request->get_route() : '';
		if ($route === '' || strpos($route, '/seojusai/v1') !== 0) {
			return $result;
		}

		$method = is_object($request) && method_exists($request, 'get_method') ? strtoupper((string) $request->get_method()) : 'GET';
		if (in_array($method, ['GET', 'HEAD', 'OPTIONS'], true)) {
			return $result;
		}

		return new \WP_REST_Response([
			'success' => false,
			'error'   => __('Доступ лише для читання. Дії через REST заблоковано.', 'seojusai'),
			'code'    => 'seojusai_rest_readonly',
		], 405);
	}

	/**
	 * Регистрация всех контроллеров
	 */
	public function init_controllers(): void {

		foreach ($this->controllers as $class) {

			if (!class_exists($class)) {
				continue;
			}

			try {
				$controller = new $class();

				if (!$controller instanceof RestControllerInterface) {
					continue;
				}

				$controller->register_routes();

			} catch (\Throwable $e) {
				if (class_exists(Logger::class)) {
			Logger::error('rest_kernel_error', ['message' => '[SEOJusAI REST] ' . $class . ': ' . $e->getMessage()]);
		}
			}
		}
	}


	/**
	 * Guard: payload size limit to prevent DoS via huge JSON bodies
	 */
	public static function guard_payload($result, $server, $request) {
		$route = is_object($request) && method_exists($request, 'get_route') ? (string) $request->get_route() : '';
		if ($route === '' || strpos($route, '/seojusai/v1') !== 0) {
			return $result;
		}

		// Rate limit to reduce abuse on heavy endpoints (chat/audit/apply).
		$limit = self::rate_limit_for_route($route);
		if ($limit > 0) {
			if (!self::check_rate_limit($route, $limit)) {
				return new \WP_REST_Response([
					'success' => false,
					'error'   => 'Too many requests',
					'code'    => 'rate_limited',
				], 429);
			}
		}

		// Content-Length header if present
		$len = 0;
		if (is_object($request) && method_exists($request, 'get_header')) {
			$len = (int) $request->get_header('content-length');
		}
		if (!$len && isset($_SERVER['CONTENT_LENGTH'])) {
			$len = (int) $_SERVER['CONTENT_LENGTH'];
		}

		// 512KB hard limit
		if ($len > 524288) {
			return new \WP_REST_Response([
				'success' => false,
				'error'   => 'Payload too large',
				'code'    => 'payload_too_large',
			], 413);
		}

		return $result;
	}

	/**
	 * Determine per-route rate limit (requests per minute).
	 */
	private static function rate_limit_for_route(string $route): int {
		// Heavy endpoints
		if (strpos($route, '/seojusai/v1/chat') === 0) {
			return 20;
		}
		if (strpos($route, '/seojusai/v1/page-audit') === 0) {
			return 30;
		}
		if (strpos($route, '/seojusai/v1/apply') === 0 || strpos($route, '/seojusai/v1/bulk') === 0) {
			return 60;
		}
		if (strpos($route, '/seojusai/v1/schema/') === 0) {
			return 60;
		}
		// Default
		return 120;
	}

	/**
	 * Simple per-user/IP rate limiter using transients.
	 */
	private static function check_rate_limit(string $route, int $maxPerMinute): bool {
		$uid = function_exists('get_current_user_id') ? (int) get_current_user_id() : 0;
		$ip  = isset($_SERVER['REMOTE_ADDR']) ? (string) $_SERVER['REMOTE_ADDR'] : '0.0.0.0';
		$bucket = (string) floor(time() / 60);
		$hash = substr(hash('sha256', $route . '|' . $uid . '|' . $ip . '|' . $bucket), 0, 32);
		$key = 'seojusai_rl_' . $hash;
		$cnt = (int) get_transient($key);
		$cnt++;
		set_transient($key, $cnt, 70);
		return $cnt <= $maxPerMinute;
	}

	/**
	 * Только администратор
	 */
	public static function can_manage($request = null): bool {
		if (!CapabilityGuard::can(CapabilityMap::MANAGE_SETTINGS)) {
			return false;
		}

		// Rate limit admin management calls (REST)
		if (defined('REST_REQUEST') && REST_REQUEST) {
			$route = '';
			if (is_object($request) && method_exists($request, 'get_route')) {
				$route = (string) $request->get_route();
			}
			$bucket = 'rest:manage';
			if (!RateLimiter::allow($bucket, 60, 60)) {
				return false;
			}
		}

		// REST nonce.
		//
		// IMPORTANT:
		// Our /seojusai/v1 namespace is guarded as read-only (see guard_readonly_namespace).
		// That means all routes are GET/HEAD/OPTIONS only.
		// Requiring X-WP-Nonce for GET breaks:
		// - direct browser checks of /wp-json/... while logged in
		// - admin-side fetch() calls that rely on cookie auth
		// Rank Math style: allow authenticated admin GET requests without nonce.
		// Keep nonce requirement only for non-GET (even though they are blocked anyway)
		// to stay fail-closed if guard_readonly_namespace changes in the future.
		$nonce = '';
		if (is_object($request) && method_exists($request, 'get_header')) {
			$nonce = (string) $request->get_header('X-WP-Nonce');
		}
		if (!$nonce && isset($_SERVER['HTTP_X_WP_NONCE'])) {
			$nonce = (string) $_SERVER['HTTP_X_WP_NONCE'];
		}

		if (defined('REST_REQUEST') && REST_REQUEST) {
			$method = 'GET';
			if (is_object($request) && method_exists($request, 'get_method')) {
				$method = strtoupper((string) $request->get_method());
			} elseif (isset($_SERVER['REQUEST_METHOD'])) {
				$method = strtoupper((string) $_SERVER['REQUEST_METHOD']);
			}

			// ✅ Allow read-only requests for authenticated admins without requiring a nonce.
			if (in_array($method, ['GET', 'HEAD', 'OPTIONS'], true)) {
				return is_user_logged_in();
			}

			// ❌ For any mutating method, require valid REST nonce.
			return $nonce ? (bool) wp_verify_nonce($nonce, 'wp_rest') : false;
		}

		return true;
	}

	/**
	 * Выполнение (audit / chat / apply)
	 * ⚠️ ПОКА = admin only
	 */
	public static function can_execute($request = null): bool {
		if (!self::can_manage($request)) {
			// allow execution for analysts/operators with specific caps
			if (!CapabilityGuard::any([
				CapabilityMap::RUN_ANALYSIS,
				CapabilityMap::RUN_AUTOPILOT,
				CapabilityMap::APPLY_CHANGES,
				CapabilityMap::VIEW_REPORTS,
			], [])) {
				return false;
			}
		}

		// Rate limit execution entrypoints (REST)
		if (defined('REST_REQUEST') && REST_REQUEST) {
			$route = '';
			if (is_object($request) && method_exists($request, 'get_route')) {
				$route = (string) $request->get_route();
			}
			$bucket = 'rest:execute';
			$limit = 30;
			$window = 60;
			if (strpos($route, '/apply') !== false) { $bucket = 'rest:apply'; $limit = 10; $window = 60; }
			if (strpos($route, '/bulk') !== false) { $bucket = 'rest:bulk'; $limit = 2; $window = 60; }
			if (strpos($route, '/autopilot') !== false) { $bucket = 'rest:autopilot'; $limit = 5; $window = 300; }
			if (!RateLimiter::allow($bucket, $limit, $window)) {
				return false;
			}
		}
		// Nonce is still required for REST context
		$nonce = '';
		if (is_object($request) && method_exists($request, 'get_header')) {
			$nonce = (string) $request->get_header('X-WP-Nonce');
		}
		if (!$nonce && isset($_SERVER['HTTP_X_WP_NONCE'])) {
			$nonce = (string) $_SERVER['HTTP_X_WP_NONCE'];
		}
		if (defined('REST_REQUEST') && REST_REQUEST) {
			return $nonce ? (bool) wp_verify_nonce($nonce, 'wp_rest') : false;
		}
		return true;
	}
}
