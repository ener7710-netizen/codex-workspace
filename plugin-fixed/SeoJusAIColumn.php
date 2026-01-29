<?php
declare(strict_types=1);

namespace SEOJusAI\Admin\ListColumns;

use SEOJusAI\Analyze\PageAuditSummary;
use SEOJusAI\Background\Scheduler;

defined('ABSPATH') || exit;

/**
 * Adds RankMath-like column with issue counter to Posts/Pages list tables.
 */
final class SeoJusAIColumn {

    public function register(): void {
        // Columns
        add_filter('manage_posts_columns', [ $this, 'add_column' ], 20);
        add_filter('manage_pages_columns', [ $this, 'add_column' ], 20);

        add_action('manage_posts_custom_column', [ $this, 'render_column' ], 20, 2);
        add_action('manage_pages_custom_column', [ $this, 'render_column' ], 20, 2);

        // Групова обробка actions
        add_filter('bulk_actions-edit-post', [ $this, 'add_bulk_action' ], 20);
        add_filter('bulk_actions-edit-page', [ $this, 'add_bulk_action' ], 20);

        add_filter('handle_bulk_actions-edit-post', [ $this, 'handle_bulk_action' ], 20, 3);
        add_filter('handle_bulk_actions-edit-page', [ $this, 'handle_bulk_action' ], 20, 3);

        add_action('admin_notices', [ $this, 'notices' ]);
    }

    public function add_column(array $columns): array {
        $columns['seojusai'] = 'SEOJusAI';
        return $columns;
    }

    public function render_column(string $column, int $post_id): void {
        if ($column !== 'seojusai') {
            return;
        }

        $summary = PageAuditSummary::load($post_id);
        $counts = is_array($summary) ? ($summary['counts'] ?? null) : null;

        $total = is_array($counts) ? (int) ($counts['total'] ?? 0) : 0;
        $crit  = is_array($counts) ? (int) ($counts['critical'] ?? 0) : 0;
        $warn  = is_array($counts) ? (int) ($counts['warning'] ?? 0) : 0;

        $class = 'seojusai-badge';
        if ($crit > 0) {
            $class .= ' is-critical';
        } elseif ($warn > 0) {
            $class .= ' is-warning';
        } else {
            $class .= ' is-ok';
        }

        $title = 'Натисни для детального аналізу в редакторі (SEOJusAI sidebar)';
        echo '<span class="' . esc_attr($class) . '" title="' . esc_attr($title) . '">' . esc_html((string) $total) . '</span>';
    }

    public function add_bulk_action(array $actions): array {
        if (!current_user_can('manage_options')) {
            return $actions;
        }
        $actions['seojusai_bulk_refresh'] = 'SEOJusAI: Оновити аналіз (bulk)';
        return $actions;
    }

    public function handle_bulk_action(string $redirect_url, string $action, array $post_ids): string {
        if ($action !== 'seojusai_bulk_refresh') {
            return $redirect_url;
        }
        if (!current_user_can('manage_options')) {
            return add_query_arg('seojusai_bulk_refresh', 'denied', $redirect_url);
        }

        $queued = 0;
        foreach ($post_ids as $post_id) {
            $post_id = (int) $post_id;
            if ($post_id <= 0) continue;
            $this->queue_refresh($post_id);
            $queued++;
        }

        return add_query_arg('seojusai_bulk_refresh', (string) $queued, $redirect_url);
    }

    private function queue_refresh(int $post_id): void {
        if (function_exists('as_enqueue_async_action')) {
            as_enqueue_async_action(Scheduler::HOOK_AUDIT_REFRESH_POST, [ 'post_id' => $post_id ], 'seojusai');
            return;
        }
        // fallback immediate
        if (class_exists('SEOJusAI\\Tasks\\AuditPostJob')) {
            \SEOJusAI\Tasks\AuditPostJob::run($post_id);
        }
    }

    public function notices(): void {
        if (!isset($_GET['seojusai_bulk_refresh'])) {
            return;
        }
        $val = sanitize_text_field((string) wp_unslash($_GET['seojusai_bulk_refresh']));
        if ($val === 'denied') {
            echo '<div class="notice notice-error"><p>SEOJusAI: Немає прав для bulk refresh.</p></div>';
            return;
        }
        $n = (int) $val;
        if ($n > 0) {
            echo '<div class="notice notice-success"><p>SEOJusAI: Поставлено в чергу оновлення аналізу для ' . esc_html((string) $n) . ' сторінок.</p></div>';
        }
    }
}
