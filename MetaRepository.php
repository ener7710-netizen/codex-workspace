<?php
declare(strict_types=1);

namespace SEOJusAI\Meta;

defined('ABSPATH') || exit;

final class MetaRepository {

	public const KEY_TITLE       = '_seojusai_meta_title';
	public const KEY_DESCRIPTION = '_seojusai_meta_description';
	public const KEY_CANONICAL   = '_seojusai_meta_canonical';
	public const KEY_ROBOTS      = '_seojusai_meta_robots'; // index|noindex,follow|nofollow

	public const KEY_OG_TITLE       = '_seojusai_og_title';
	public const KEY_OG_DESCRIPTION = '_seojusai_og_description';
	public const KEY_OG_IMAGE       = '_seojusai_og_image';

	public const KEY_TW_TITLE       = '_seojusai_tw_title';
	public const KEY_TW_DESCRIPTION = '_seojusai_tw_description';
	public const KEY_TW_IMAGE       = '_seojusai_tw_image';

	public function get(int $post_id): array {
		return [
			'title'       => (string) get_post_meta($post_id, self::KEY_TITLE, true),
			'description' => (string) get_post_meta($post_id, self::KEY_DESCRIPTION, true),
			'canonical'   => (string) get_post_meta($post_id, self::KEY_CANONICAL, true),
			'robots'      => (string) get_post_meta($post_id, self::KEY_ROBOTS, true),

			'og_title'       => (string) get_post_meta($post_id, self::KEY_OG_TITLE, true),
			'og_description' => (string) get_post_meta($post_id, self::KEY_OG_DESCRIPTION, true),
			'og_image'       => (string) get_post_meta($post_id, self::KEY_OG_IMAGE, true),

			'tw_title'       => (string) get_post_meta($post_id, self::KEY_TW_TITLE, true),
			'tw_description' => (string) get_post_meta($post_id, self::KEY_TW_DESCRIPTION, true),
			'tw_image'       => (string) get_post_meta($post_id, self::KEY_TW_IMAGE, true),
		];
	}

	public function save(int $post_id, array $data): void {
		$map = [
			self::KEY_TITLE       => 'title',
			self::KEY_DESCRIPTION => 'description',
			self::KEY_CANONICAL   => 'canonical',
			self::KEY_ROBOTS      => 'robots',

			self::KEY_OG_TITLE       => 'og_title',
			self::KEY_OG_DESCRIPTION => 'og_description',
			self::KEY_OG_IMAGE       => 'og_image',

			self::KEY_TW_TITLE       => 'tw_title',
			self::KEY_TW_DESCRIPTION => 'tw_description',
			self::KEY_TW_IMAGE       => 'tw_image',
		];

		foreach ($map as $meta_key => $field) {
			$value = $data[$field] ?? '';
			$value = is_string($value) ? $value : '';
			$value = trim($value);

			if ($meta_key === self::KEY_CANONICAL) {
				$value = $value !== '' ? esc_url_raw($value) : '';
			} elseif (in_array($meta_key, [self::KEY_OG_IMAGE, self::KEY_TW_IMAGE], true)) {
				$value = $value !== '' ? esc_url_raw($value) : '';
			} elseif ($meta_key === self::KEY_ROBOTS) {
				$value = preg_replace('~[^a-z,]~', '', strtolower($value));
				$value = $value ?: '';
			} else {
				$value = wp_kses_post($value);
			}

			if ($value === '') {
				delete_post_meta($post_id, $meta_key);
			} else {
				update_post_meta($post_id, $meta_key, $value);
			}
		}
	}
}
