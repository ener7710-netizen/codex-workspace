<?php
declare(strict_types=1);

namespace SEOJusAI\Schema;

defined('ABSPATH') || exit;

/**
 * SchemaStorage
 *
 * Зберігає JSON-LD schema на рівні поста.
 * Використовується для Preview/Apply з редактора.
 */
final class SchemaStorage {

	private const META_KEY = '_seojusai_schema_jsonld';

	public function get(int $post_id): string {
		$raw = get_post_meta($post_id, self::META_KEY, true);
		return is_string($raw) ? $raw : '';
	}

	public function set(int $post_id, string $json): bool {
		return (bool) update_post_meta($post_id, self::META_KEY, $json);
	}

	public function delete(int $post_id): void {
		delete_post_meta($post_id, self::META_KEY);
	}
}
