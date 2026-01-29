<?php
declare(strict_types=1);

namespace SEOJusAI\SaaS\Usage;

use SEOJusAI\SaaS\TenantContext;
use wpdb;

defined('ABSPATH') || exit;

final class UsageService {

    private static ?bool $vectors_has_tenant = null;

    private static function table_exists(string $table): bool {
        global $wpdb;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
        $exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table));
        return !empty($exists);
    }

    private static function vectors_has_tenant_column(): bool {
        if (self::$vectors_has_tenant !== null) {
            return self::$vectors_has_tenant;
        }
        global $wpdb;
        $table = $wpdb->prefix . 'seojusai_vectors';
        if (!self::table_exists($table)) {
            self::$vectors_has_tenant = false;
            return false;
        }
        // NOTE: identifier interpolation is OK here because $table is built from $wpdb->prefix + constant suffix.
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
        $col = $wpdb->get_var($wpdb->prepare("SHOW COLUMNS FROM {$table} LIKE %s", 'tenant_id'));
        self::$vectors_has_tenant = !empty($col);
        return self::$vectors_has_tenant;
    }

    public static function count_vectors(): int {
        global $wpdb;
        $table = $wpdb->prefix . 'seojusai_vectors';
        if (!self::table_exists($table)) return 0;

        if (self::vectors_has_tenant_column()) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
            return (int) $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(1) FROM {$table} WHERE tenant_id=%s",
                TenantContext::tenant_id()
            ));
        }

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
        return (int) $wpdb->get_var("SELECT COUNT(1) FROM {$table}");
    }

    public static function audits_today(): int {
        global $wpdb;
        $table = $wpdb->prefix . 'seojusai_tasks';
        if (!self::table_exists($table)) return 0;

        $start = strtotime('today midnight');
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
        return (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(1) FROM {$table} WHERE action=%s AND created_at >= FROM_UNIXTIME(%d)",
            'page_audit',
            $start
        ));
    }

    public static function bulk_apply_today(): int {
        global $wpdb;
        $table = $wpdb->prefix . 'seojusai_bulk_jobs';
        if (!self::table_exists($table)) return 0;

        $start = strtotime('today midnight');
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
        return (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(1) FROM {$table} WHERE action=%s AND created_at >= FROM_UNIXTIME(%d)",
            'apply',
            $start
        ));
    }
}
