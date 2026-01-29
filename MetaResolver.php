<?php
declare(strict_types=1);

namespace SEOJusAI\Meta;

defined('ABSPATH') || exit;

final class MetaResolver {

	public function __construct(private MetaRepository $repo) {}

	public function resolve(int $post_id): array {
		$meta = $this->repo->get($post_id);

		// Fallbacks
		if ($meta['title'] === '') {
			$meta['title'] = (string) get_the_title($post_id);
		}
		if ($meta['description'] === '') {
			$excerpt = (string) get_post_field('post_excerpt', $post_id);
			if ($excerpt === '') {
				$content = (string) get_post_field('post_content', $post_id);
				$content = wp_strip_all_tags($content);
				$excerpt = mb_substr(trim($content), 0, 160);
			}
			$meta['description'] = trim($excerpt);
		}

		return $meta;
	}
}
