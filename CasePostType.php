<?php
declare(strict_types=1);

namespace SEOJusAI\CaseLearning;
use SEOJusAI\Input\Input;

defined('ABSPATH') || exit;

final class CasePostType {

    public const CPT = 'seojusai_case';

    public static function register(): void {

        $labels = [
            'name'               => __('Кейси', 'seojusai'),
            'singular_name'      => __('Кейс', 'seojusai'),
            'add_new'            => __('Додати кейс', 'seojusai'),
            'add_new_item'       => __('Додати новий кейс', 'seojusai'),
            'edit_item'          => __('Редагувати кейс', 'seojusai'),
            'new_item'           => __('Новий кейс', 'seojusai'),
            'view_item'          => __('Переглянути кейс', 'seojusai'),
            'search_items'       => __('Пошук кейсів', 'seojusai'),
            'not_found'          => __('Кейсів не знайдено', 'seojusai'),
            'not_found_in_trash' => __('У кошику кейсів не знайдено', 'seojusai'),
            'menu_name'          => __('Кейси', 'seojusai'),
        ];

        register_post_type(self::CPT, [
            'labels' => $labels,
            'public' => false,
            'show_ui' => true,
            'show_in_menu' => false, // додаємо в меню SEOJusAI вручну
            'supports' => ['title','editor'],
            'capability_type' => 'post',
            'map_meta_cap' => true,
            'has_archive' => false,
            'rewrite' => false,
        ]);

        add_action('add_meta_boxes', [self::class, 'register_metaboxes']);
        add_action('save_post_' . self::CPT, [self::class, 'save_meta'], 10, 2);
    }

    public static function register_metaboxes(): void {
        add_meta_box(
            'seojusai_case_meta',
            __('Параметри кейсу', 'seojusai'),
            [self::class, 'render_metabox'],
            self::CPT,
            'side',
            'default'
        );
    }

    public static function render_metabox(\WP_Post $post): void {
        wp_nonce_field('seojusai_case_meta', 'seojusai_case_meta_nonce');

        $practice = (string) get_post_meta($post->ID, '_seojusai_case_practice', true);
        $outcome  = (string) get_post_meta($post->ID, '_seojusai_case_outcome', true);
        $action   = (string) get_post_meta($post->ID, '_seojusai_case_action_key', true);

        $practice = $practice ?: 'criminal';
        $outcome = $outcome ?: 'positive';

        ?>
        <p>
            <label for="seojusai_case_practice"><strong><?php echo esc_html__('Сфера', 'seojusai'); ?></strong></label><br/>
            <select name="seojusai_case_practice" id="seojusai_case_practice">
                <option value="criminal" <?php selected($practice, 'criminal'); ?>><?php echo esc_html__('Кримінальне право', 'seojusai'); ?></option>
                <option value="tax" <?php selected($practice, 'tax'); ?>><?php echo esc_html__('Податкове право', 'seojusai'); ?></option>
                <option value="civil" <?php selected($practice, 'civil'); ?>><?php echo esc_html__('Цивільне / сімейне право', 'seojusai'); ?></option>
            </select>
        </p>

        <p>
            <label for="seojusai_case_outcome"><strong><?php echo esc_html__('Результат', 'seojusai'); ?></strong></label><br/>
            <select name="seojusai_case_outcome" id="seojusai_case_outcome">
                <option value="positive" <?php selected($outcome, 'positive'); ?>><?php echo esc_html__('Позитивний', 'seojusai'); ?></option>
                <option value="neutral" <?php selected($outcome, 'neutral'); ?>><?php echo esc_html__('Нейтральний', 'seojusai'); ?></option>
                <option value="negative" <?php selected($outcome, 'negative'); ?>><?php echo esc_html__('Негативний', 'seojusai'); ?></option>
            </select>
        </p>

        <p>
            <label><strong><?php echo esc_html__('Ключ дії', 'seojusai'); ?></strong></label><br/>
            <code style="display:block; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;"><?php echo esc_html($action ?: __('(не задано)', 'seojusai')); ?></code>
        </p>

        <p style="margin:0;">
            <em><?php echo esc_html__('Не додавайте персональні дані. Це внутрішні навчальні записи.', 'seojusai'); ?></em>
        </p>
        <?php
    }

    public static function save_meta(int $post_id, \WP_Post $post): void {
        if (!(Input::post('seojusai_case_meta_nonce', null) !== null) || !wp_verify_nonce((string)Input::post('seojusai_case_meta_nonce'), 'seojusai_case_meta')) {
            return;
        }
        if (!current_user_can('edit_post', $post_id)) return;

        $practice = (Input::post('seojusai_case_practice', null) !== null) ? sanitize_key((string)Input::post('seojusai_case_practice')) : 'criminal';
        $outcome  = (Input::post('seojusai_case_outcome', null) !== null) ? sanitize_key((string)Input::post('seojusai_case_outcome')) : 'neutral';

        if (!in_array($practice, ['criminal','tax','civil'], true)) $practice = 'criminal';
        if (!in_array($outcome, ['positive','neutral','negative'], true)) $outcome = 'neutral';

        update_post_meta($post_id, '_seojusai_case_practice', $practice);
        update_post_meta($post_id, '_seojusai_case_outcome', $outcome);
    }
}
