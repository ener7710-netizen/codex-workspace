<?php
declare(strict_types=1);

namespace SEOJusAI\Snapshots;

use WP_Error;

defined('ABSPATH') || exit;

/**
 * SnapshotService
 * Високорівневий сервіс для створення бекапів контенту та їх відновлення.
 */
final class SnapshotService {

	private SnapshotRepository $repo;

	public function __construct(?SnapshotRepository $repo = null) {
		$this->repo = $repo ?? new SnapshotRepository();
	}

	public function repo(): SnapshotRepository {
		return $this->repo;
	}

	/**
	 * Створити снапшот сторінки перед змінами AI.
	 */
	public function capture_post(int $post_id, string $label = 'manual', array $context = []): int {
		$post_id = (int) $post_id;
		if ( $post_id <= 0 ) {
			return 0;
		}

		$post = get_post($post_id);
		if ( ! $post || empty($post->ID) ) {
			return 0;
		}

		// Збираємо всі мета-дані (важливо для Lawyer SEO)
		$meta = get_post_meta($post_id);
		if ( ! is_array($meta) ) {
			$meta = [];
		}

		$data = [
			'kind'       => 'post',
			'label'      => sanitize_key($label),
			'captured_at'=> time(),
			'context'    => $context,
			'post'       => [
				'ID'           => (int) $post->ID,
				'post_type'    => (string) $post->post_type,
				'post_status'  => (string) $post->post_status,
				'post_title'   => (string) $post->post_title,
				'post_content' => (string) $post->post_content,
				'post_excerpt' => (string) $post->post_excerpt,
				'post_name'    => (string) $post->post_name,
			],
			'meta' => $meta,
		];

		return $this->repo->insert('post', $post_id, $data);
	}

	/**
	 * Відкотити пост до попереднього стану.
	 *
	 * @return true|WP_Error
	 */
	public function restore_post_snapshot(int $snapshot_id) {
		$snapshot_id = (int) $snapshot_id;
		if ( $snapshot_id <= 0 ) {
			return new WP_Error('seojusai_bad_snapshot', 'Некоректний snapshot_id.');
		}

		$row = $this->repo->get($snapshot_id);
		if ( ! $row ) {
			return new WP_Error('seojusai_snapshot_missing', 'Snapshot не знайдено.');
		}

		// У репозиторії ми використовуємо поле 'type'
		if ( (string) ($row['type'] ?? '') !== 'post' ) {
			return new WP_Error('seojusai_snapshot_type', 'Цей snapshot не є типом post.');
		}

		$post_id = (int) ($row['post_id'] ?? 0);
		$data = (array) ($row['data'] ?? []);
		$post_data = $data['post'] ?? [];
		$meta_data = $data['meta'] ?? [];

		if ( $post_id <= 0 || empty($post_data) ) {
			return new WP_Error('seojusai_snapshot_corrupt', 'Дані снапшота пошкоджені.');
		}

		// 1. Оновлення основних полів поста
		$update = [
			'ID'           => $post_id,
			'post_title'   => (string) $post_data['post_title'],
			'post_content' => (string) $post_data['post_content'],
			'post_excerpt' => (string) $post_data['post_excerpt'],
		];

		// Використовуємо wp_slash для безпечного запису в БД WordPress
		$updated = wp_update_post(wp_slash($update), true);
		if ( is_wp_error($updated) ) {
			return $updated;
		}

		// 2. Відновлення мета-даних
		foreach ( $meta_data as $key => $values ) {
			$key = (string) $key;
			if ( $key === '' || is_protected_meta($key, 'post') ) {
				continue;
			}

			// Очищуємо поточні значення перед відновленням
			delete_post_meta($post_id, $key);

			if ( is_array($values) ) {
				foreach ( $values as $v ) {
					// meta_data у WordPress приходить як масив рядків, можливо серіалізованих
					add_post_meta($post_id, $key, maybe_unserialize($v));
				}
			}
		}

		/**
		 * Подія після відновлення: дозволяє очистити кеш
		 * або записати подію в seojusai_trace.
		 */
		do_action('seojusai/snapshots/restored', [
			'snapshot_id' => $snapshot_id,
			'post_id'     => $post_id,
			'timestamp'   => time(),
		]);

		return true;
	}
}
