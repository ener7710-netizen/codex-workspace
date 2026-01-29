<?php
declare(strict_types=1);

namespace SEOJusAI\SaaS;

defined('ABSPATH') || exit;

/**
 * Tenant/account context for SaaS-ready mode.
 * Local-first: for a single WP site, tenant_id is persisted as option.
 */
final class TenantContext {

    private const OPT_TENANT_ID  = 'seojusai_tenant_id';
    private const OPT_ACCOUNT_ID = 'seojusai_account_id';
    private const OPT_PLAN       = 'seojusai_plan';
    private const OPT_SAAS_MODE  = 'seojusai_saas_mode'; // local|remote (foundation)

    public static function ensure_ids(): void {
        if (!get_option(self::OPT_TENANT_ID)) {
            update_option(self::OPT_TENANT_ID, wp_generate_uuid4(), false);
        }
        if (!get_option(self::OPT_ACCOUNT_ID)) {
            update_option(self::OPT_ACCOUNT_ID, wp_generate_uuid4(), false);
        }
        if (!get_option(self::OPT_PLAN)) {
            update_option(self::OPT_PLAN, 'local', false);
        }
        if (!get_option(self::OPT_SAAS_MODE)) {
            update_option(self::OPT_SAAS_MODE, 'local', false);
        }
    }

    public static function tenant_id(): string {
        $id = (string) get_option(self::OPT_TENANT_ID, '');
        return $id !== '' ? $id : '';
    }

    public static function account_id(): string {
        $id = (string) get_option(self::OPT_ACCOUNT_ID, '');
        return $id !== '' ? $id : '';
    }

    public static function plan(): string {
        return sanitize_key((string) get_option(self::OPT_PLAN, 'local'));
    }

    public static function saas_mode(): string {
        $m = sanitize_key((string) get_option(self::OPT_SAAS_MODE, 'local'));
        return $m === 'remote' ? 'remote' : 'local';
    }

    public static function set_plan(string $plan): void {
        update_option(self::OPT_PLAN, sanitize_key($plan), false);
    }

    public static function set_saas_mode(string $mode): void {
        $mode = sanitize_key($mode);
        update_option(self::OPT_SAAS_MODE, $mode === 'remote' ? 'remote' : 'local', false);
    }
}
