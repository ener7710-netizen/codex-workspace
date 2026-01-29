<?php
declare(strict_types=1);

namespace SEOJusAI\Decisions;

defined('ABSPATH') || exit;

final class DecisionTables {

    private const VERSION = 1;
    private const OPTION_KEY = 'seojusai_decision_db_ver';

    public static function ensure(): void {
        $ver = (int) get_option(self::OPTION_KEY, 0);
        if ($ver >= self::VERSION) return;

        global $wpdb;

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $charset = $wpdb->get_charset_collate();

        $t1 = $wpdb->prefix . 'seojusai_decisions';
        $t2 = $wpdb->prefix . 'seojusai_outcomes';

        $sql1 = "CREATE TABLE {$t1} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            created_at INT(11) NOT NULL,
            source VARCHAR(32) NOT NULL DEFAULT 'unknown',
            context_type VARCHAR(16) NOT NULL DEFAULT 'page',
            post_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
            explain_id VARCHAR(64) NOT NULL DEFAULT '',
            risk_level VARCHAR(16) NOT NULL DEFAULT 'unknown',
            confidence DECIMAL(5,2) NOT NULL DEFAULT 0.00,
            decision_hash VARCHAR(64) NOT NULL DEFAULT '',
            actions_json LONGTEXT NULL,
            meta LONGTEXT NULL,
            PRIMARY KEY (id),
            KEY post_created (post_id, created_at),
            KEY src_created (source, created_at),
            KEY hash (decision_hash)
        ) {$charset};";

        $sql2 = "CREATE TABLE {$t2} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            decision_id BIGINT(20) UNSIGNED NOT NULL,
            created_at INT(11) NOT NULL,
            status VARCHAR(16) NOT NULL DEFAULT 'unknown',
            snapshot_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
            lead_delta INT(11) NOT NULL DEFAULT 0,
            conversion_delta INT(11) NOT NULL DEFAULT 0,
            trust_delta INT(11) NOT NULL DEFAULT 0,
            meta LONGTEXT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY decision_unique (decision_id),
            KEY status_created (status, created_at)
        ) {$charset};";

        dbDelta($sql1);
        dbDelta($sql2);

        update_option(self::OPTION_KEY, self::VERSION, false);
    }
}
