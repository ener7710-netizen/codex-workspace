<?php
declare(strict_types=1);

namespace SEOJusAI\Crawl;

use SEOJusAI\Core\EmergencyStop;
use WP_Post;

if ( ! defined('ABSPATH') ) {
	exit;
}

/**
 * SiteTreeBuilder
 *
 * Побудова ієрархії сайту (pages / CPT).
 * ТІЛЬКИ структура, без SEO-логіки.
 */
final class SiteTreeBuilder {

	/**
	 * Побудувати дерево сайту.
	 *
	 * @param array<string,mixed> $args
	 * @return array<int,array<string,mixed>>
	 */
	public function build(array $args = []): array {

		if ( EmergencyStop::is_active() ) {
			return [];
		}

		$defaults = [
			'post_type'      => ['page'],
			'post_status'    => ['publish'],
			'posts_per_page' => -1,
			'orderby'        => ['menu_order' => 'ASC', 'ID' => 'ASC'],
		];

		$qargs = wp_parse_args($args, $defaults);

		$posts = get_posts($qargs);

		if ( empty($posts) ) {
			return [];
		}

		$nodes = [];
		foreach ( $posts as $post ) {
			if ( ! $post instanceof WP_Post ) {
				continue;
			}

			$nodes[ $post->ID ] = [
				'id'        => $post->ID,
				'parent'    => (int) $post->post_parent,
				'title'     => get_the_title($post),
				'url'       => get_permalink($post),
				'type'      => $post->post_type,
				'status'    => $post->post_status,
				'order'     => (int) $post->menu_order,
				'children'  => [],
			];
		}

		$tree = [];

		foreach ( $nodes as $id => &$node ) {
			if ( $node['parent'] > 0 && isset($nodes[ $node['parent'] ]) ) {
				$nodes[ $node['parent'] ]['children'][] =& $node;
			} else {
				$tree[] =& $node;
			}
		}
		unset($node);

		return $tree;
	}

	/**
	 * Плоский список сторінок з depth.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	public function flatten(array $tree): array {

		$out = [];

		$this->walk($tree, $out, 0);

		return $out;
	}

	/**
	 * Рекурсивний обхід.
	 *
	 * @param array<int,array<string,mixed>> $nodes
	 * @param array<int,array<string,mixed>> $out
	 */
	private function walk(array $nodes, array &$out, int $depth): void {

		foreach ( $nodes as $node ) {
			$item = $node;
			$item['depth'] = $depth;
			unset($item['children']);

			$out[] = $item;

			if ( ! empty($node['children']) ) {
				$this->walk($node['children'], $out, $depth + 1);
			}
		}
	}
}
