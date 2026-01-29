<?php
declare(strict_types=1);

namespace SEOJusAI\Admin\MetaBoxes;

defined('ABSPATH') || exit;

final class ManualLockMetaBox {

    public function register(): void {
        add_action('add_meta_boxes', [$this, 'add_box']);
        add_action('save_post', [$this, 'save']);
    }

    public function add_box(): void {
        add_meta_box(
            'seojusai_manual_lock',
            __('SEOJusAI Autopilot', 'seojusai'),
            [$this, 'render'],
            null,
            'side',
            'high'
        );
    }

    public function render($post): void {
        $locked = (bool) get_post_meta($post->ID, 'seojusai_manual_lock', true);
        wp_nonce_field('seojusai_manual_lock', 'seojusai_manual_lock_nonce');
        ?>
        <label>
            <input type="checkbox" name="seojusai_manual_lock" value="1" <?php checked($locked); ?> />
            <?php esc_html_e('Disable Autopilot for this post', 'seojusai'); ?>
        </label>
        <?php
    }

    public function save(int $post_id): void {
        if (!isset($_POST['seojusai_manual_lock_nonce']) ||
            !wp_verify_nonce($_POST['seojusai_manual_lock_nonce'], 'seojusai_manual_lock')) {
            return;
        }
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
        if (!current_user_can('edit_post', $post_id)) return;

        if (isset($_POST['seojusai_manual_lock'])) {
            update_post_meta($post_id, 'seojusai_manual_lock', 1);
        } else {
            delete_post_meta($post_id, 'seojusai_manual_lock');
        }
    }
}