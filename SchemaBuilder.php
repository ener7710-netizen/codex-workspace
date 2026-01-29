<?php
declare(strict_types=1);

namespace SEOJusAI\Schema\Builder;

defined('ABSPATH') || exit;

final class SchemaBuilder {

	public const META_TYPE = '_seojusai_schema_type';
	public const META_DATA = '_seojusai_schema_data';
	public const META_JSON = '_seojusai_custom_schema';

	public function build(int $post_id, string $type, array $data): array {
		$type = sanitize_key($type);
		$name = (string) get_the_title($post_id);
		$url = (string) get_permalink($post_id);

		switch ($type) {
			case 'legalservice':
				$city = trim((string)($data['city'] ?? ''));
				$phone = trim((string)($data['phone'] ?? ''));
				return [
					'@context' => 'https://schema.org',
					'@type' => 'LegalService',
					'name' => $name,
					'url' => $url,
					'areaServed' => $city ? ['@type'=>'City','name'=>$city] : null,
					'telephone' => $phone ?: null,
				];
			case 'article':
				$author = trim((string)($data['author'] ?? ''));
				return [
					'@context' => 'https://schema.org',
					'@type' => 'Article',
					'headline' => $name,
					'mainEntityOfPage' => $url,
					'author' => $author ? ['@type'=>'Person','name'=>$author] : null,
					'datePublished' => get_the_date('c', $post_id),
					'dateModified' => get_post_modified_time('c', true, $post_id),
				];
			default:
				return [];
		}
	}

	public function persist(int $post_id, string $type, array $data): void {
		$type = sanitize_key($type);
		if ($type === '') {
			delete_post_meta($post_id, self::META_TYPE);
			delete_post_meta($post_id, self::META_DATA);
			delete_post_meta($post_id, self::META_JSON);
			return;
		}

		update_post_meta($post_id, self::META_TYPE, $type);
		update_post_meta($post_id, self::META_DATA, wp_json_encode($data, JSON_UNESCAPED_UNICODE));

		$schema = $this->build($post_id, $type, $data);
		// cleanup nulls
		$schema = array_filter($schema, static fn($v)=> $v !== null);
		if (!empty($schema)) {
			update_post_meta($post_id, self::META_JSON, wp_json_encode($schema, JSON_UNESCAPED_UNICODE));
		} else {
			delete_post_meta($post_id, self::META_JSON);
		}
	}
}
