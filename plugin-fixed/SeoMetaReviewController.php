<?php
declare(strict_types=1);

namespace SEOJusAI\Admin;

use SEOJusAI\Repository\SeoMetaRepository;

defined('ABSPATH')||exit;

final class SeoMetaReviewController {

    public function register(): void {
        add_action('admin_post_seojusai_seo_meta_approve', [$this,'approve']);
        add_action('admin_post_seojusai_seo_meta_reject', [$this,'reject']);
    }

    public function approve(): void {
        $this->handle('approved');
    }

    public function reject(): void {
        $this->handle('rejected');
    }

    private function handle(string $status): void {
        if (!current_user_can('manage_options')) {
            wp_die('Forbidden');
        }

        // Verify nonce used in action links
        check_admin_referer('seojusai_decision_action');

        $hash = sanitize_text_field($_GET['decision_hash'] ?? '');
        if (!$hash) {
            wp_die('Missing');
        }

        SeoMetaRepository::mark($hash, $status);
        wp_redirect(wp_get_referer());
        exit;
    }
}
