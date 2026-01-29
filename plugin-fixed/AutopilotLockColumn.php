<?php
declare(strict_types=1);

namespace SEOJusAI\Admin\ListColumns;

defined('ABSPATH') || exit;

/**
 * Adds a lock icon to posts/pages list table
 * indicating that SEOJusAI Autopilot is disabled for the item.
 */
final class AutopilotLockColumn {

    public function register(): void {
        add_filter('manage_posts_columns', [$this, 'add_column'], 20);
        add_filter('manage_pages_columns', [$this, 'add_column'], 20);

        add_action('manage_posts_custom_column', [$this, 'render_column'], 20, 2);
        add_action('manage_pages_custom_column', [$this, 'render_column'], 20, 2);

        add_action('admin_head', [$this, 'styles']);
    }

    public function add_column(array $columns): array {
        if (!isset($columns['seojusai_autopilot'])) {
            $columns['seojusai_autopilot'] = __('Autopilot', 'seojusai');
        }
        return $columns;
    }

    public function render_column(string $column, int $post_id): void {
        if ($column !== 'seojusai_autopilot') {
            return;
        }

        $locked = (bool) get_post_meta($post_id, 'seojusai_manual_lock', true);
        if (!$locked) {
            return;
        }

        echo '<span class="seojusai-lock" title="' . esc_attr__('Autopilot disabled', 'seojusai') . '">🔒</span>';
    }

    public function styles(): void {
        echo '<style>
            .wp-list-table .column-seojusai_autopilot { width: 90px; text-align: center; }
            .seojusai-lock { font-size: 16px; line-height: 1; }
        </style>';
    }
}