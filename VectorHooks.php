<?php
declare(strict_types=1);

namespace SEOJusAI\Vectors;

defined('ABSPATH') || exit;

final class VectorHooks {

    public static function register(): void {
        add_action('save_post', [self::class, 'on_save_post'], 20, 3);
        add_action('deleted_post', [self::class, 'on_deleted_post'], 10, 1);
    }

    public static function on_save_post(int $post_id, \WP_Post $post, bool $update): void {
        if (wp_is_post_revision($post_id) || wp_is_post_autosave($post_id)) return;
        if (!\SEOJusAI\seojusai_should_process_post($post_id)) return;
        if ($post->post_status !== 'publish') return;

        // debounce: only schedule once per 2 minutes per post
        $k = 'seojusai_vec_debounce_' . $post_id;
        $last = (int) get_transient($k);
        if ($last && (time() - $last) < 120) return;
        set_transient($k, time(), 120);

        VectorRebuilder::schedule_index_post($post_id, VectorNamespaces::POSTS);
    }

    public static function on_deleted_post(int $post_id): void {
        // we don't delete immediately; old vectors belong to version and will be dropped on next rebuild.
        // optional: could purge for current version
    }
}
