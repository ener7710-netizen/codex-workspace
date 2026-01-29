<?php
declare(strict_types=1);

namespace SEOJusAI;

defined('ABSPATH') || exit;

if (!function_exists(__NAMESPACE__ . '\\seojusai_should_process_post')) {
    function seojusai_should_process_post(int $post_id): bool {

        if ($post_id <= 0) {
            return false;
        }

        if (wp_is_post_revision($post_id) || wp_is_post_autosave($post_id)) {
            return false;
        }

        if (!in_array(get_post_type($post_id), ['post', 'page'], true)) {
            return false;
        }

        $last_run = get_post_meta($post_id, '_seojusai_last_run', true);
        if ($last_run && (time() - (int) $last_run) < 900) { // 15 minutes
            return false;
        }

        $content  = (string) get_post_field('post_content', $post_id);
        $new_hash = md5(wp_strip_all_tags($content));

        $old_hash = (string) get_post_meta($post_id, '_seojusai_content_hash', true);
        if ($old_hash !== '' && hash_equals($old_hash, $new_hash)) {
            return false;
        }

        return true;
    }
}
