<?php
declare(strict_types=1);

namespace SEOJusAI\AIMonitoring\Conversion;

defined('ABSPATH') || exit;

final class ConversionTable {

    private const VERSION = 1;
    private const OPTION_KEY = 'seojusai_conv_db_ver';

    public static function ensure(): void {
        $ver = (int) get_option(self::OPTION_KEY, 0);
        if ($ver >= self::VERSION) return;

        global $wpdb;

        $table = $wpdb->prefix . 'seojusai_conversions';
        $charset = $wpdb->get_charset_collate();

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $sql = "CREATE TABLE {$table} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            ts INT(11) NOT NULL,
            post_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
            source VARCHAR(32) NOT NULL DEFAULT 'unknown',
            event_type VARCHAR(32) NOT NULL DEFAULT 'unknown',
            session_id VARCHAR(64) NOT NULL DEFAULT '',
            value_int INT(11) NOT NULL DEFAULT 0,
            meta LONGTEXT NULL,
            PRIMARY KEY  (id),
            KEY ts (ts),
            KEY post_ts (post_id, ts),
            KEY src_ts (source, ts),
            KEY evt_ts (event_type, ts)
        ) {$charset};";

        dbDelta($sql);

        update_option(self::OPTION_KEY, self::VERSION, false);
    }
}
