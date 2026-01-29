<?php
declare(strict_types=1);

namespace SEOJusAI\Admin;

use SEOJusAI\SEO\SeoMetaApplier;

defined('ABSPATH')||exit;

final class SeoMetaApplyController {

    public function register(): void {
        add_action('admin_post_seojusai_seo_meta_apply', [$this,'apply']);
    }

    public function apply(): void {
        if (!current_user_can('manage_options')) {
            wp_die('Forbidden');
        }

        // Verify nonce from link
        check_admin_referer('seojusai_decision_action');

        $hash = sanitize_text_field($_GET['decision_hash'] ?? '');
        if (!$hash) {
            wp_die('Missing decision');
        }

        SeoMetaApplier::apply($hash);

        wp_redirect(wp_get_referer());
        exit;
    }
}
