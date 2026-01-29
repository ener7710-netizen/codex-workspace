<?php
declare(strict_types=1);

namespace SEOJusAI\Install;

defined('ABSPATH') || exit;

/**
 * PageSpeedHistory
 * * Відповідає за створення таблиці історії метрик та надання методів
 * для запису та очищення логів швидкості.
 */
final class PageSpeedHistory {

	/**
	 * Створення таблиці при активації плагіна або через Database\Tables.
	 */
	public static function install(): void {
		global $wpdb;

		$table = $wpdb->prefix . 'seojusai_pagespeed_history';
		$charset = $wpdb->get_charset_collate();

		/**
		 * Додано поле INP (Interaction to Next Paint), яке є ключовим у 2026 році,
		 * та FCP для повного аналізу першого відмальовування.
		 */
		$sql = "
		CREATE TABLE {$table} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			url TEXT NOT NULL,
			strategy VARCHAR(20) NOT NULL DEFAULT 'mobile',
			performance INT NULL,
			lcp FLOAT NULL,
			cls FLOAT NULL,
			inp FLOAT NULL,
			fcp FLOAT NULL,
			created_at DATETIME NOT NULL,
			PRIMARY KEY (id),
			KEY url (url(191)),
			KEY created_at (created_at)
		) {$charset};
		";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta($sql);
	}

	/**
	 * Збереження нового запису в історію.
	 * * @param array $metrics Масив з даними: url, strategy, performance, lcp, cls, inp, fcp.
	 */
	public static function push(array $metrics): bool {
		global $wpdb;
		$table = $wpdb->prefix . 'seojusai_pagespeed_history';

		return (bool) $wpdb->insert(
			$table,
			[
				'url'         => esc_url_raw($metrics['url'] ?? ''),
				'strategy'    => sanitize_key($metrics['strategy'] ?? 'mobile'),
				'performance' => (int) ($metrics['performance'] ?? 0),
				'lcp'         => (float) ($metrics['lcp'] ?? 0),
				'cls'         => (float) ($metrics['cls'] ?? 0),
				'inp'         => (float) ($metrics['inp'] ?? 0),
				'fcp'         => (float) ($metrics['fcp'] ?? 0),
				'created_at'  => current_time('mysql'),
			],
			['%s', '%s', '%d', '%f', '%f', '%f', '%f', '%s']
		);
	}

	/**
	 * Повне очищення історії.
	 */
	public static function clear(): void {
		global $wpdb;
		$table = $wpdb->prefix . 'seojusai_pagespeed_history';
		$wpdb->query("TRUNCATE TABLE {$table}");
	}
}
