<?php
declare(strict_types=1);

namespace SEOJusAI\Editor;

use SEOJusAI\Input\Input;

defined('ABSPATH') || exit;

/**
 * EditorSidebar
 *
 * Відповідає лише за:
 * - підключення скриптів/стилів бокової панелі Gutenberg
 * - реєстрацію meta-полів, потрібних UI (show_in_rest)
 */
final class EditorSidebar {

    public const SCRIPT_HANDLE = 'seojusai-editor-sidebar';

    public function register(): void {
        add_action('init', [$this, 'register_meta'], 5);
        add_action('enqueue_block_editor_assets', [$this, 'enqueue'], 10);
    }

    public function register_meta(): void {
        // Потрібно для читання/оновлення в редакторі через REST (core/editor).
        $auth = static function (): bool {
            return current_user_can('edit_posts');
        };

        register_post_meta('', '_seojusai_score', [
            'type'              => 'integer',
            'single'            => true,
            'show_in_rest'      => true,
            'sanitize_callback' => static function ($value): int {
                $v = (int) $value;
                if ($v < 0) { $v = 0; }
                if ($v > 100) { $v = 100; }
                return $v;
            },
            'auth_callback'     => $auth,
            'default'           => 0,
        ]);

        register_post_meta('', '_seojusai_score_updated', [
            'type'              => 'string',
            'single'            => true,
            'show_in_rest'      => true,
            'sanitize_callback' => 'sanitize_text_field',
            'auth_callback'     => $auth,
            'default'           => '',
        ]);

        register_post_meta('', '_seojusai_audit_summary', [
            'type'              => 'string',
            'single'            => true,
            'show_in_rest'      => true,
            'sanitize_callback' => static function ($value): string {
                $v = is_string($value) ? $value : '';
                $v = trim($v);
                if ($v === '') {
                    return '';
                }
                $decoded = json_decode($v, true);
                if (!is_array($decoded)) {
                    return '';
                }
                return wp_json_encode($decoded, JSON_UNESCAPED_UNICODE);
            },
            'auth_callback'     => $auth,
            'default'           => '',
        ]);
    }

    public function enqueue(): void {
        $screen = function_exists('get_current_screen') ? get_current_screen() : null;
        if ($screen && isset($screen->base) && $screen->base !== 'post') {
            return;
        }

        wp_enqueue_script(
            self::SCRIPT_HANDLE,
            SEOJUSAI_URL . 'assets/js/sidebar.js',
            [
                'wp-plugins',
                'wp-edit-post',
                'wp-element',
                'wp-components',
                'wp-data',
                'wp-api-fetch',
                'wp-i18n',
            ],
            defined('SEOJUSAI_VERSION') ? (string) SEOJUSAI_VERSION : '1.0.0',
            true
        );

        // Легкий CSS для панелі (якщо існує)
        $css = SEOJUSAI_PATH . 'assets/admin/admin-ui.css';
        if (is_string($css) && file_exists($css)) {
            wp_enqueue_style(
                'seojusai-editor-sidebar',
                SEOJUSAI_URL . 'assets/admin/admin-ui.css',
                [],
                defined('SEOJUSAI_VERSION') ? (string) SEOJUSAI_VERSION : '1.0.0'
            );
        }

        // Fetch the current post ID safely from query args or POST, falling back to 0
        $post_id = Input::get_int('post', 0);
        if ($post_id <= 0) {
            $post_id = Input::post_int('post_ID', 0);
        }

        wp_localize_script(
            self::SCRIPT_HANDLE,
            'SEOJusAIEditor',
            [
                'nonce'   => wp_create_nonce('wp_rest'),
                'postId'  => $post_id,
                'restUrl' => esc_url_raw((string) get_rest_url(null, 'seojusai/v1')),
                'i18n'    => [
                    'title'          => 'SEOJusAI',
                    'issuesTab'      => 'Проблеми',
                    'chatTab'        => 'Чат з ІІ',
                    'refresh'        => 'Оновити перевірку',
                    'computing'      => 'Виконується перевірка…',
                    'queued'         => 'Перевірку поставлено в чергу. Оновіть сторінку або натисніть «Оновити перевірку» через кілька секунд.',
                    'noIssues'       => 'Проблем не знайдено.',
                    'scoreLabel'     => 'Оцінка SEOJusAI',
                    'calibrationTitle' => 'Післярелізна стабільність',
                    'calibStable'      => 'Стабільна',
                    'calibCalibrating' => 'Калібрується',
                    'calibUnknown'     => 'Невідомо',
                    'calibNote'        => 'Під час калібрування критичні зміни можуть бути обмежені політиками безпеки.',
                    'lastUpdate'     => 'Останнє оновлення',
                    'send'           => 'Надіслати',
                    'messageLabel'   => 'Повідомлення',
                    'messageHelp'    => 'Питайте про SEO, структуру, Schema.org та покращення для цієї сторінки.',
                    'emptyMessage'   => 'Введіть повідомлення.',
                    'loadingChat'    => 'Завантаження…',
                    'errorGeneric'   => 'Сталася помилка. Спробуйте ще раз.',
                ],
            ]
        );
    }
}
