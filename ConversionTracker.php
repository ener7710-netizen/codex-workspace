<?php
declare(strict_types=1);

namespace SEOJusAI\AIMonitoring\Conversion;

defined('ABSPATH') || exit;

final class ConversionTracker {

    public static function register(): void {
        add_action('init', [ConversionTable::class, 'ensure'], 9);
        add_action('wp_enqueue_scripts', [self::class, 'enqueue'], 20);
    }

    public static function enqueue(): void {
        if (is_admin()) return;

        $handle = 'seojusai-conversion';
        $src = plugins_url('assets/js/conversion.js', dirname(__DIR__, 3) . '/seojusai.php');

        wp_enqueue_script($handle, $src, [], defined('SEOJUSAI_VERSION') ? SEOJUSAI_VERSION : '1.0.0', true);

        $post_id = 0;
        if (function_exists('get_queried_object_id')) {
            $post_id = (int) get_queried_object_id();
        }

        $payload = [
            'endpoint' => esc_url_raw(rest_url('seojusai/v1/conversion')),
            'nonce' => wp_create_nonce('seojusai_conversion'),
            'post_id' => $post_id,
            'site' => home_url('/'),
        ];

        wp_add_inline_script($handle, 'window.SEOJusAIConv=' . wp_json_encode($payload) . ';', 'before');
    }
}
