<?php
declare(strict_types=1);

namespace SEOJusAI\Admin;

use SEOJusAI\AI\SelfTrainingService;

defined('ABSPATH') || exit;

final class SelfTrainingController {

    public function register(): void {
        add_action('admin_post_seojusai_self_train_now', [$this, 'run']);
    }

    public function run(): void {
        if (!current_user_can('manage_options')) {
            wp_die('Forbidden');
        }
        check_admin_referer('seojusai_self_train_now');

        $limit = (int) get_option('seojusai_self_training_max_samples', 500);
        if ($limit < 50) $limit = 50;

        foreach (['page_type','practice_area','search_intent'] as $tax) {
            SelfTrainingService::train($tax, $limit);
        }

        wp_redirect(add_query_arg('seojusai_notice', 'self_trained', wp_get_referer()));
        exit;
    }
}