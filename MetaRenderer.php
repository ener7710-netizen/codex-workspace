<?php
declare(strict_types=1);

namespace SEOJusAI\Meta;

defined('ABSPATH') || exit;

final class MetaRenderer {

	private MetaResolver $resolver;

	public function __construct() {
		$this->resolver = new MetaResolver(new MetaRepository());
	}

	public function register(): void {
		add_filter('pre_get_document_title', [$this, 'filter_title'], 20);
		add_action('wp_head', [$this, 'render_head'], 1);
	}

	public function filter_title(string $title): string {
		if (is_admin() || !is_singular()) {
			return $title;
		}
		$post_id = (int) get_queried_object_id();
		if ($post_id <= 0) {
			return $title;
		}
		$meta = $this->resolver->resolve($post_id);
		return $meta['title'] !== '' ? wp_strip_all_tags($meta['title']) : $title;
	}

	public function render_head(): void {
		if (is_admin() || !is_singular()) {
			return;
		}
		$post_id = (int) get_queried_object_id();
		if ($post_id <= 0) {
			return;
		}
		$meta = $this->resolver->resolve($post_id);

		$desc = trim((string) $meta['description']);
		if ($desc !== '') {
			echo "\n" . '<meta name="description" content="' . esc_attr(wp_strip_all_tags($desc)) . '">' . "\n";
		}

		$canonical = trim((string) $meta['canonical']);
		if ($canonical === '') {
			$canonical = get_permalink($post_id);
		}
		if ($canonical) {
			echo '<link rel="canonical" href="' . esc_url($canonical) . '">' . "\n";
		}

		$robots = trim((string) $meta['robots']);
		if ($robots !== '') {
			echo '<meta name="robots" content="' . esc_attr($robots) . '">' . "\n";
		}

		$this->render_social($meta, $canonical);
	}

	private function render_social(array $meta, string $canonical): void {
		$og_title = trim((string) $meta['og_title']) ?: trim((string) $meta['title']);
		$og_desc  = trim((string) $meta['og_description']) ?: trim((string) $meta['description']);
		$og_image = trim((string) $meta['og_image']);

		if ($og_title !== '') {
			echo '<meta property="og:title" content="' . esc_attr(wp_strip_all_tags($og_title)) . '">' . "\n";
		}
		if ($og_desc !== '') {
			echo '<meta property="og:description" content="' . esc_attr(wp_strip_all_tags($og_desc)) . '">' . "\n";
		}
		if ($canonical !== '') {
			echo '<meta property="og:url" content="' . esc_url($canonical) . '">' . "\n";
		}
		echo '<meta property="og:type" content="article">' . "\n";
		if ($og_image !== '') {
			echo '<meta property="og:image" content="' . esc_url($og_image) . '">' . "\n";
		}

		$tw_title = trim((string) $meta['tw_title']) ?: $og_title;
		$tw_desc  = trim((string) $meta['tw_description']) ?: $og_desc;
		$tw_image = trim((string) $meta['tw_image']) ?: $og_image;

		if ($tw_title !== '') {
			echo '<meta name="twitter:title" content="' . esc_attr(wp_strip_all_tags($tw_title)) . '">' . "\n";
		}
		if ($tw_desc !== '') {
			echo '<meta name="twitter:description" content="' . esc_attr(wp_strip_all_tags($tw_desc)) . '">' . "\n";
		}
		if ($tw_image !== '') {
			echo '<meta name="twitter:image" content="' . esc_url($tw_image) . '">' . "\n";
			echo '<meta name="twitter:card" content="summary_large_image">' . "\n";
		}
	}
}
