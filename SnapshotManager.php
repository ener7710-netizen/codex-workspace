<?php
declare(strict_types=1);

namespace SEOJusAI\Core;
use SEOJusAI\Utils\Logger;

defined('ABSPATH') || exit;

/**
 * SnapshotManager
 * Агрегує дані для AI Engine та забезпечує можливість відкату правок.
 */
final class SnapshotManager {

	/**
	 * Створити повний знімок для конкретної сторінки.
	 * Зроблено статичним для зручного виклику з RestHandler або Engine.
	 */
	public static function capture_page_snapshot(int $post_id): ?int {
		global $wpdb;

		// Перевірка Emergency Stop
		if (get_option('seojusai_emergency_stop', false)) {
			return null;
		}

		$post = get_post($post_id);
		if (!$post) return null;

		try {
			// 1. Збираємо локальні дані (з розгортанням блоків Gutenberg)
			// Використовуємо метод з нашого Engine для отримання реального тексту
			$full_content = \SEOJusAI\AI\Engine::analyze_post_content_only($post_id);

			$snapshot_data = [
				'post_id'    => $post_id,
				'url'        => get_permalink($post_id),
				'title'      => $post->post_title,
				'content'    => $post->post_content, // Зберігаємо сирий код для Rollback
				'clean_text' => $full_content,       // Зберігаємо чистий текст для AI
				'meta'       => get_post_meta($post_id),
				'created_at' => current_time('mysql'),
			];

			// 2. Спроба додати дані GSC (якщо клас існує)
			if (class_exists('\SEOJusAI\GSC\GscServiceAccount')) {
				// Тут можна додати логіку запиту метрик, якщо GSC підключено
				$snapshot_data['gsc_metrics'] = [];
			}

			// 3. Збереження в базу
			$table = $wpdb->prefix . 'seojusai_snapshots';
			$inserted = $wpdb->insert(
				$table,
				[
					'entity_type' => 'post',
					'entity_id'   => $post_id,
					'data_json'   => wp_json_encode($snapshot_data),
					'created_at'  => current_time('mysql'),
				],
				['%s', '%d', '%s', '%s']
			);

			return $inserted ? (int) $wpdb->insert_id : null;

		} catch (\Throwable $e) {
			if (defined('WP_DEBUG') && WP_DEBUG) {
				if (class_exists(Logger::class)) {
			Logger::error('snapshot_error', ['message' => 'SEOJusAI Snapshot Error: ' . $e->getMessage()]);
		}
			}
			return null;
		}
	}

	/**
	 * Отримати останній знімок
	 */
	public static function get_latest(int $post_id): ?array {
		global $wpdb;
		$table = $wpdb->prefix . 'seojusai_snapshots';

		$row = $wpdb->get_row($wpdb->prepare(
			"SELECT data_json FROM {$table} WHERE entity_id = %d ORDER BY created_at DESC LIMIT 1",
			$post_id
		));

		return $row ? json_decode($row->data_json, true) : null;
	}
}
