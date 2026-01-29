<?php
declare(strict_types=1);

namespace SEOJusAI\Breadcrumbs;

use SEOJusAI\Core\ModuleRegistry;

defined('ABSPATH') || exit;

final class Breadcrumbs {

	public function register(): void {
		add_shortcode('seojusai_breadcrumbs', [$this, 'shortcode']);
	}

	/**
	 * @param array<string, mixed> $atts
	 */
	public function shortcode(array $atts = []): string {

		// Frontend rendering only for singular content.
		if (!is_singular()) {
			return '';
		}

		$registry = ModuleRegistry::instance();
		if (!$registry->can_init('breadcrumbs')) {
			return '';
		}

		$trail = $this->build_trail();
		$trail = apply_filters('seojusai/breadcrumbs/trail', $trail);

		if (empty($trail)) {
			return '';
		}

		$parts = [];
		foreach ($trail as $t) {
			$title = esc_html((string) ($t['title'] ?? ''));
			$url   = (string) ($t['url'] ?? '');

			if ($url !== '') {
				$parts[] = '<a href="' . esc_url($url) . '">' . $title . '</a>';
				continue;
			}

			$parts[] = '<span class="seojusai-bc-current">' . $title . '</span>';
		}

		return '<nav class="seojusai-breadcrumbs" aria-label="breadcrumbs">' . implode(' &raquo; ', $parts) . '</nav>';
	}

	/**
	 * @return array<int, array{title:string, url:string}>
	 */
	private function build_trail(): array {

		$trail = [];
		$trail[] = [
			'title' => (string) __('Головна', 'seojusai'),
			'url'   => (string) home_url('/'),
		];

		$post = get_queried_object();
		if (!$post || empty($post->ID)) {
			return $trail;
		}

		$parents = [];
		$pid     = (int) $post->post_parent;

		while ($pid > 0) {
			$p = get_post($pid);
			if (!$p) {
				break;
			}

			$parents[] = [
				'title' => (string) get_the_title($pid),
				'url'   => (string) get_permalink($pid),
			];

			$pid = (int) $p->post_parent;
		}

		$parents = array_reverse($parents);
		foreach ($parents as $p) {
			$trail[] = $p;
		}

		$trail[] = [
			'title' => (string) get_the_title((int) $post->ID),
			'url'   => '',
		];

		return $trail;
	}
}
