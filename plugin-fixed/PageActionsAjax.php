<?php
declare(strict_types=1);

namespace SEOJusAI\Admin\Ajax;

use SEOJusAI\Input\Input;
use SEOJusAI\PageActions\PageActionExecutionService;

defined('ABSPATH') || exit;

/**
 * PageActionsAjax
 *
 * Адмін AJAX виклики для застосування/відкату Page-level AI actions.
 *
 * ⚠️ Причина: namespace /seojusai/v1 у REST є read-only (execution заборонено).
 * Тому мутації виконуються лише через wp-admin AJAX з nonce + manage_options.
 */
final class PageActionsAjax {

    public function __construct() {
        add_action('wp_ajax_seojusai_page_action_apply', [$this, 'apply']);
        add_action('wp_ajax_seojusai_page_action_rollback', [$this, 'rollback']);
    }

    public function apply(): void {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'forbidden'], 403);
        }

        check_ajax_referer('seojusai_page_actions_exec', '_ajax_nonce');

        $post_id = Input::post_int('post_id', 0, PHP_INT_MAX);
        $type = Input::string(Input::post('type', ''), 64, true);
        $value = (string) Input::post('value', '');

        $svc = new PageActionExecutionService();
        $res = $svc->apply((int)$post_id, $type, $value);

        if (!($res['ok'] ?? false)) {
            wp_send_json_error([
                'message' => $this->human_error((string)($res['error'] ?? 'error')),
                'code' => (string)($res['error'] ?? 'error'),
            ], 400);
        }

        wp_send_json_success($res);
    }

    public function rollback(): void {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'forbidden'], 403);
        }

        check_ajax_referer('seojusai_page_actions_exec', '_ajax_nonce');

        $snapshot_id = Input::post_int('snapshot_id', 0, PHP_INT_MAX);
        $svc = new PageActionExecutionService();
        $res = $svc->rollback((int)$snapshot_id);

        if (!($res['ok'] ?? false)) {
            wp_send_json_error([
                'message' => __('Не вдалося виконати відкат.', 'seojusai'),
                'code' => (string)($res['error'] ?? 'rollback_failed'),
            ], 400);
        }

        wp_send_json_success($res);
    }

    private function human_error(string $code): string {
        return match ($code) {
            'invalid_input' => __('Некоректні вхідні дані.', 'seojusai'),
            'unsupported_action' => __('Ця дія не підтримується для застосування.', 'seojusai'),
            'snapshot_failed' => __('Не вдалося створити snapshot для відкату.', 'seojusai'),
            default => __('Помилка виконання дії.', 'seojusai'),
        };
    }
}
