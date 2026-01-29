<?php
declare(strict_types=1);

namespace SEOJusAI\Core;

defined('ABSPATH') || exit;

use SEOJusAI\AI\Engine;
use SEOJusAI\Input\Input;

/**
 * Клас для обробки AJAX-запитів адмін-панелі.
 */
final class Ajax {

    public static function init(): void {
        $self = new self();
		// @boundary Execution is controlled exclusively by AutopilotExecutionLoop.
		// ❌ Removed: Execution must occur only via AutopilotExecutionLoop.
		// add_action('wp_ajax_seojusai_create_page', [$self, 'handle_create_page']);
        add_action('wp_ajax_seojusai_toggle_module', [$self, 'handle_toggle_module']);
    }

    /**
     * Створення нової сторінки-чернетки на основі рекомендації ШІ
     */
    public function handle_create_page(): void {
		// @boundary Execution is controlled exclusively by AutopilotExecutionLoop.
		// ❌ Manual execution disabled — Autopilot only.
		wp_send_json_error(['message' => __('Ручне створення сторінок вимкнено. Виконання дозволене лише через Autopilot.', 'seojusai')]);
		return;

        check_ajax_referer('seojusai_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Недостатньо прав.']);
        }

        $title  = Input::post_string('title', 200);
        $reason = Input::post_string('reason', 500);
if (empty($title)) {
            wp_send_json_error(['message' => 'Заголовок сторінки порожній.']);
        }

        $post_id = Engine::create_suggested_page($title, $reason);

        if ($post_id > 0) {
            wp_send_json_success([
                'message'  => 'Чернетку створено успішно!',
                'edit_url' => get_edit_post_link($post_id, 'url')
            ]);
        } else {
            wp_send_json_error(['message' => 'Не вдалося створити сторінку.']);
        }
    }

    /**
     * Перемикання стану модулів (активація/деактивація)
     */
    public function handle_toggle_module(): void {
        check_ajax_referer('seojusai_toggle_module', '_ajax_nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error();
        }

        $slug    = Input::string(Input::post('module',''), 60, true);
        $enabled = Input::string(Input::post('enabled','0'), 5, true) === '1';

        if ($slug) {
            $success = ModuleRegistry::instance()->set_enabled($slug, $enabled);
            if ($success) {
                wp_send_json_success();
            }
        }

        wp_send_json_error();
    }
}
