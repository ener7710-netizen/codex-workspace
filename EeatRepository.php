<?php
declare(strict_types=1);

namespace SEOJusAI\Eeat;

defined('ABSPATH') || exit;

final class EeatRepository {

	private const META_KEY = '_seojusai_eeat';

	public static function get(int $post_id): array {
		$data = get_post_meta($post_id, self::META_KEY, true);
		return is_array($data) ? $data : [];
	}

	public static function save(int $post_id, array $data): void {
		update_post_meta($post_id, self::META_KEY, $data);
	}
}
