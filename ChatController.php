<?php
declare(strict_types=1);

namespace SEOJusAI\Rest\Controllers;

use WP_REST_Request;
use WP_REST_Response;
use SEOJusAI\Input\Input;
use SEOJusAI\Core\EmergencyStop;
use SEOJusAI\Rest\RestKernel;
use SEOJusAI\Rest\AbstractRestController;
use SEOJusAI\Rest\Contracts\RestControllerInterface;
use SEOJusAI\AI\Chat\LegalAIChat;
use SEOJusAI\Utils\Logger;

defined('ABSPATH') || exit;

/**
 * ChatController
 *
 * REST endpoint для живого AI-чату в редакторі
 * URL: POST /seojusai/v1/chat
 */
final class ChatController extends AbstractRestController implements RestControllerInterface {

	public function register_routes(): void {

		register_rest_route('seojusai/v1', '/chat', [
			'methods'             => 'POST',
			'permission_callback' => static function () {
				// ⚠️ ВАЖНО: чат НЕ критичен → не блокируем жестко
				return current_user_can('edit_posts');
			},
			'callback'            => [ $this, 'handle' ],
		]);
	}

	public function handle(WP_REST_Request $request): WP_REST_Response {

		// 🛑 Emergency Stop
		if (class_exists(EmergencyStop::class) && EmergencyStop::is_active()) {
			return rest_ensure_response([
				'ok'    => false,
				'reply' => 'AI тимчасово вимкнено (Emergency Stop).',
			]);
		}

		$post_id = Input::int($request->get_param('post_id'), 0, 0, PHP_INT_MAX);
		$message = Input::string($request->get_param('message'), 4000, true);

		$is_learning = Input::bool($request->get_param('is_learning'), false);
		$user_id     = get_current_user_id();

		if ($post_id <= 0 || $message === '') {
			return rest_ensure_response([
				'ok'    => false,
				'reply' => 'Некоректний запит до AI-чату.',
			]);
		}

		try {

			$result = LegalAIChat::respond(
				$post_id,
				$message,
				$is_learning,
				$user_id
			);

			// Гарантируем структуру
			if (!is_array($result)) {
				throw new \RuntimeException('AI Chat returned invalid response');
			}

			// Фолбэк
			if (!isset($result['reply'])) {
				$result['reply'] = 'AI не повернув відповіді.';
			}

			return rest_ensure_response($result);

		} catch (\Throwable $e) {

			if (defined('WP_DEBUG') && WP_DEBUG) {
				if (class_exists(Logger::class)) {
			Logger::error('chat_controller_error', ['message' => '[SEOJusAI Chat Error] ' . $e->getMessage()]);
		}
			}

			return rest_ensure_response([
				'ok'    => false,
				'reply' => 'Помилка AI-чату. Спробуй пізніше.',
			]);
		}
	}
}
