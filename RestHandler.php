<?php
declare(strict_types=1);

namespace SEOJusAI\Api;

defined('ABSPATH') || exit;

use WP_REST_Request;
use WP_REST_Response;
use WP_Error;
use SEOJusAI\AI\Strategy\LegalAIStrategy; // Додаємо імпорт

final class RestHandler {

	public const NAMESPACE = 'seojusai/v1';

	public function register_routes(): void {
		// Аудит сторінки (тепер викликає Engine::analyze_post)
		register_rest_route(self::NAMESPACE, '/page-audit', [
			'methods'             => 'POST',
			'callback'            => [$this, 'handle_audit'],
			'permission_callback' => [$this, 'check_permission'],
		]);

		// Чат (тепер викликає LegalAIStrategy::chat)
		register_rest_route(self::NAMESPACE, '/chat', [
			'methods'             => 'POST',
			'callback'            => [$this, 'handle_chat'],
			'permission_callback' => [$this, 'check_permission'],
		]);

		// Інші маршрути залишаємо без змін для сумісності з JS
		register_rest_route(self::NAMESPACE, '/execute-task', [
			'methods'             => 'POST',
			'callback'            => [$this, 'handle_execute_task'],
			'permission_callback' => [$this, 'check_permission'],
		]);

		register_rest_route(self::NAMESPACE, '/save-schema', [
			'methods'             => 'POST',
			'callback'            => [$this, 'handle_save_schema'],
			'permission_callback' => [$this, 'check_permission'],
		]);
	}

	public function check_permission(): bool {
		return current_user_can('edit_posts');
	}

	public function handle_audit(WP_REST_Request $request): WP_REST_Response|WP_Error {
		$post_id = (int) $request->get_param('post_id');

		if (!$post_id) {
			return new WP_Error('invalid_id', 'Невірний ID поста', ['status' => 400]);
		}

		if (!class_exists('\SEOJusAI\AI\Engine')) {
			return new WP_Error('missing_engine', 'AI Engine не знайдено', ['status' => 500]);
		}

		try {
			// Виклик нашого нового Engine
			$audit_result = \SEOJusAI\AI\Engine::analyze_post($post_id);

			$response = wp_parse_args($audit_result, [
				'score'    => 0,
				'analysis' => [],
				'tasks'    => [],
				'schema_suggest' => ''
			]);

			return new WP_REST_Response($response, 200);
		} catch (\Throwable $e) {
			return new WP_Error('ai_audit_error', $e->getMessage(), ['status' => 500]);
		}
	}

	public function handle_chat(WP_REST_Request $request): WP_REST_Response|WP_Error {
		$post_id = (int) $request->get_param('post_id');
		$message = trim((string) $request->get_param('message'));

		if (!$message || !$post_id) {
			return new WP_Error('invalid_data', 'Дані неповні', ['status' => 400]);
		}

		// Отримуємо дані аудиту для контексту чату
		$data = get_post_meta($post_id, '_seojusai_analysis_data', true);
		if (!is_array($data)) {
			return new WP_REST_Response([
				'status' => 'success',
				'reply'  => 'Спочатку запустіть SEO аудит, щоб я міг бачити дані сторінки.'
			], 200);
		}

		try {
			// ВИКЛИКАЄМО НОВУ СТРАТЕГІЮ ЗАМІСТЬ chat_reply
			$ai_result = LegalAIStrategy::chat([
				'post_id'  => $post_id,
				'message'  => $message,
				'facts'    => $data['facts'] ?? [],
				'analysis' => $data['analysis'] ?? [],
				'tasks'    => $data['tasks'] ?? []
			]);

			return new WP_REST_Response([
				'status' => 'success',
				'reply'  => $ai_result['reply']
			], 200);
		} catch (\Throwable $e) {
			return new WP_Error('ai_chat_error', $e->getMessage(), ['status' => 500]);
		}
	}

	public function handle_execute_task(WP_REST_Request $request): WP_REST_Response|WP_Error {
		$post_id = (int) $request->get_param('post_id');
		$task    = $request->get_param('task');

		if (!$post_id || empty($task)) {
			return new WP_Error('missing_data', 'Недостатньо даних', ['status' => 400]);
		}

		$success = false;
		// Перевіряємо наявність методу в Engine, щоб не було Fatal Error
		if (method_exists('\SEOJusAI\AI\Engine', 'apply_internal_link')) {
			if (isset($task['type']) && $task['type'] === 'internal_link') {
				$success = \SEOJusAI\AI\Engine::apply_internal_link(
					$post_id,
					(string)$task['anchor'],
					(string)$task['target_url']
				);
			}
		}

		return new WP_REST_Response(['success' => $success], 200);
	}

	public function handle_save_schema(WP_REST_Request $request): WP_REST_Response|WP_Error {
		$post_id = (int) $request->get_param('post_id');
		$schema  = $request->get_param('schema');

		if (!$post_id || !$schema) {
			return new WP_Error('invalid_data', 'Немає даних для збереження', ['status' => 400]);
		}

		$decoded = is_array($schema) ? $schema : json_decode($schema, true);
		if (json_last_error() !== JSON_ERROR_NONE) {
			return new WP_Error('invalid_json', 'Код не є коректним JSON', ['status' => 400]);
		}

		update_post_meta($post_id, '_seojusai_ai_schema', $decoded);
		return new WP_REST_Response(['success' => true, 'message' => 'Схему впроваджено'], 200);
	}
}
