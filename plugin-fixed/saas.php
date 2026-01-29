<?php
declare(strict_types=1);

use SEOJusAI\SaaS\TenantContext;
use SEOJusAI\SaaS\PlanCatalog;
use SEOJusAI\SaaS\Usage\UsageService;

defined('ABSPATH') || exit;

if (!current_user_can('manage_options')) {
    wp_die(esc_html__('Недостатньо прав.', 'seojusai'));
}

TenantContext::ensure_ids();
$plan_key = TenantContext::plan();
$plan = PlanCatalog::get($plan_key);

$tenant_id  = TenantContext::tenant_id();
$account_id = TenantContext::account_id();
$mode       = TenantContext::saas_mode();

$vectors = UsageService::count_vectors();
// Count daily audits and bulk apply operations.
$audits  = UsageService::audits_today();
$bulk    = UsageService::bulk_apply_today();

?>
<div class="wrap">
    <h1><?php echo esc_html__('SEOJusAI → SaaS (Foundation)', 'seojusai'); ?></h1>

    <div class="notice notice-info">
        <p><?php echo esc_html__('Це фундамент SaaS-режиму: ізоляція, плани та ліміти. Локальний режим залишається режимом за замовчуванням.', 'seojusai'); ?></p>
    </div>

    <table class="widefat striped" style="max-width: 980px;">
        <tbody>
        <tr>
            <th><?php echo esc_html__('Account ID', 'seojusai'); ?></th>
            <td><code><?php echo esc_html($account_id); ?></code></td>
        </tr>
        <tr>
            <th><?php echo esc_html__('Tenant ID', 'seojusai'); ?></th>
            <td><code><?php echo esc_html($tenant_id); ?></code></td>
        </tr>
        <tr>
            <th><?php echo esc_html__('План', 'seojusai'); ?></th>
            <td><?php echo esc_html($plan['label'] . ' (' . $plan_key . ')'); ?></td>
        </tr>
        <tr>
            <th><?php echo esc_html__('Compute mode', 'seojusai'); ?></th>
            <td><?php echo esc_html($mode); ?></td>
        </tr>
        </tbody>
    </table>

    <h2 style="margin-top: 20px;"><?php echo esc_html__('Ліміти та використання (сьогодні/всього)', 'seojusai'); ?></h2>

    <table class="widefat striped" style="max-width: 980px;">
        <thead>
        <tr>
            <th><?php echo esc_html__('Метрика', 'seojusai'); ?></th>
            <th><?php echo esc_html__('Використано', 'seojusai'); ?></th>
            <th><?php echo esc_html__('Ліміт', 'seojusai'); ?></th>
        </tr>
        </thead>
        <tbody>
        <tr>
            <td><?php echo esc_html__('Вектори (усього)', 'seojusai'); ?></td>
            <td><?php echo esc_html((string) $vectors); ?></td>
            <td><?php echo esc_html((string) $plan['max_vectors']); ?></td>
        </tr>
        <tr>
            <td><?php echo esc_html__('Audits (сьогодні)', 'seojusai'); ?></td>
            <td><?php echo esc_html((string) $audits); ?></td>
            <td><?php echo esc_html((string) $plan['max_audits_per_day']); ?></td>
        </tr>
        <tr>
            <td><?php echo esc_html__('Групова обробка apply (сьогодні)', 'seojusai'); ?></td>
            <td><?php echo esc_html((string) $bulk); ?></td>
            <td><?php echo esc_html((string) ($plan['max_bulk_apply_per_day'] ?? 0)); ?></td>
        </tr>
        </tbody>
    </table>

</div>
