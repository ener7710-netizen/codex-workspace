<?php
declare(strict_types=1);

namespace SEOJusAI\Admin;

use SEOJusAI\Repository\DecisionRepository;

defined('ABSPATH') || exit;

final class DecisionReviewController {

    public function register(): void {
        add_action('admin_post_seojusai_decision_approve', [$this, 'approve']);
        add_action('admin_post_seojusai_decision_reject', [$this, 'reject']);
    }

    public function approve(): void {
        $this->handle('approved');
    }

    public function reject(): void {
        $this->handle('cancelled');
    }

    private function handle(string $status): void {
        // Only administrators can perform decision actions
        if (!current_user_can('manage_options')) {
            wp_die('Forbidden');
        }

        // Verify nonce for CSRF protection. Use same action as in wp_nonce_url()
        check_admin_referer('seojusai_decision_action');

        $hash = sanitize_text_field($_GET['decision_hash'] ?? '');
        if (!$hash) {
            wp_die('Missing decision');
        }

        if ($status === 'approved') {
            DecisionRepository::mark_executed($hash);
        } else {
            DecisionRepository::mark_cancelled($hash, 'Rejected by human review');
        }

        wp_redirect(wp_get_referer());
        exit;
    }
}
