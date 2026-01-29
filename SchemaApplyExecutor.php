<?php
declare(strict_types=1);

namespace SEOJusAI\Executors;

use SEOJusAI\Core\EmergencyStop;
use SEOJusAI\Schema\SchemaRenderer;

defined('ABSPATH') || exit;

final class SchemaApplyExecutor {

	private const META_KEY = '_seojusai_schema_jsonld';

	private bool $registered = false;

	public function register(): void {
		if ( $this->registered ) {
			return;
		}
		$this->registered = true;

		// Запуск через ExecutorResolver
		add_action('seojusai/executor/add_schema', [$this, 'handle'], 10, 1);
	}

	/**
	 * @param array<string,mixed> $task
	 */
	public function handle(array $task): void {

		if ( EmergencyStop::is_active() ) {
			return;
		}

		$post_id = (int) ($task['post_id'] ?? 0);
		$type    = isset($task['type']) ? (string) $task['type'] : '';

		if ( $post_id <= 0 || $type === '' ) {
			do_action('seojusai/executor/error', [
				'action' => 'add_schema',
				'error'  => 'bad_task_payload',
				'task'   => $task,
			]);
			return;
		}

		$type = sanitize_text_field($type);

		// Мінімальний JSON-LD (safe fallback)
		$jsonld = [
			'@context' => 'https://schema.org',
			'@type'    => $type,
		];

		// Якщо SchemaRenderer має корисні методи — використаємо, але без жорстких припущень
		if ( class_exists(SchemaRenderer::class) ) {
			try {
				$renderer = new SchemaRenderer();

				// Спроба 1: render(array $graph): string
				if ( method_exists($renderer, 'render') ) {
					$rendered = $renderer->render($jsonld);
					if ( is_string($rendered) && $rendered !== '' ) {
						// якщо renderer повертає вже JSON або <script> — збережемо як є
						$this->store($post_id, $rendered, $type);
						$this->ok($post_id, $type);
						return;
					}
				}

				// Спроба 2: render_json(array $graph): string
				if ( method_exists($renderer, 'render_json') ) {
					$rendered = $renderer->render_json($jsonld);
					if ( is_string($rendered) && $rendered !== '' ) {
						$this->store($post_id, $rendered, $type);
						$this->ok($post_id, $type);
						return;
					}
				}
			} catch (\Throwable $e) {
				// fallback нижче
			}
		}

		// Fallback: зберігаємо чистий JSON
		$this->store($post_id, wp_json_encode($jsonld, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), $type);
		$this->ok($post_id, $type);
	}

	private function store(int $post_id, string $payload, string $type): void {
		$existing = get_post_meta($post_id, self::META_KEY, true);

		$data = [];
		if ( is_string($existing) && $existing !== '' ) {
			$decoded = json_decode($existing, true);
			if ( is_array($decoded) ) {
				$data = $decoded;
			}
		}

		// Зберігаємо по типу
		$data[$type] = $payload;

		update_post_meta($post_id, self::META_KEY, wp_json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
	}

	private function ok(int $post_id, string $type): void {
		do_action('seojusai/executor/success', [
			'action'    => 'add_schema',
			'post_id'   => $post_id,
			'type'      => $type,
			'timestamp' => time(),
		]);
	}
}
