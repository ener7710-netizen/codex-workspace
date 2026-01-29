<?php
declare(strict_types=1);

namespace SEOJusAI\Core;

defined('ABSPATH') || exit;

final class DBManager {

	public static function init(): void {
		global $wpdb;
		$charset_collate = $wpdb->get_charset_collate();

		$tables = [
			// 1. Таблиця блокувань (Mutex)
			"CREATE TABLE IF NOT EXISTS {$wpdb->prefix}seojusai_locks (
				lock_name varchar(100) NOT NULL,
				created_at bigint(20) NOT NULL,
				expires_at bigint(20) NOT NULL,
				PRIMARY KEY  (lock_name),
				KEY expires_at (expires_at)
			) $charset_collate;",

			// 2. Таблиця знімків (Snapshots)
			"CREATE TABLE IF NOT EXISTS {$wpdb->prefix}seojusai_snapshots (
				id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
				entity_type varchar(50) NOT NULL,
				entity_id bigint(20) NOT NULL,
				data_json longtext NOT NULL,
				created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
				PRIMARY KEY  (id),
				KEY entity (entity_type, entity_id)
			) $charset_collate;",

			// 3. Таблиця пояснень (Explanations)
			"CREATE TABLE IF NOT EXISTS {$wpdb->prefix}seojusai_explanations (
				id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
				entity_type varchar(50) NOT NULL,
				entity_id bigint(20) NOT NULL,
				decision_hash varchar(64) NOT NULL,
				explanation longtext NOT NULL,
				risk_level varchar(20) DEFAULT 'low' NOT NULL,
				source varchar(50) DEFAULT 'ai' NOT NULL,
				created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
				PRIMARY KEY  (id),
				KEY entity (entity_type, entity_id)
			) $charset_collate;",

			// 4. Таблиця впливу (Impact)
			"CREATE TABLE IF NOT EXISTS {$wpdb->prefix}seojusai_impact (
				id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
				post_id bigint(20) NOT NULL,
				snapshot_id bigint(20) NOT NULL,
				action_type varchar(50) NOT NULL,
				meta_data text,
				created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
				PRIMARY KEY  (id),
				KEY post_id (post_id)
			) $charset_collate;",

			// 5. Таблиця бази знань (KBE)
			"CREATE TABLE IF NOT EXISTS {$wpdb->prefix}seojusai_kbe (
				id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
				topic varchar(255) NOT NULL,
				content longtext NOT NULL,
				vector_id varchar(100),
				last_updated datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP NOT NULL,
				PRIMARY KEY  (id),
				KEY topic (topic)
			) $charset_collate;"
		];

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		foreach ($tables as $sql) {
			dbDelta($sql);
		}
	}
}
