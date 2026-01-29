<?php
declare(strict_types=1);

namespace SEOJusAI\Schema;

defined('ABSPATH') || exit;

use SEOJusAI\Core\EmergencyStop;

/**
 * SchemaRenderer
 * Відповідає за виведення JSON-LD розмітки у фронтенд.
 */
final class SchemaRenderer {

    /**
     * Реєстрація хука у секції <head>.
     */
    public function register(): void {
        add_action('wp_head', [$this, 'render'], 20);
    }

    /**
     * Вивід розмітки.
     */
    public function render(): void {
        // Якщо активована "Червона кнопка" або EmergencyStop — не виводимо
        if ( class_exists('\SEOJusAI\Core\EmergencyStop') && \SEOJusAI\Core\EmergencyStop::is_active() ) {
            return;
        }

        if ( ! is_singular() ) {
            return;
        }

        $post_id = get_queried_object_id();
        if ( ! $post_id ) {
            return;
        }

        $schemas = $this->collect_schemas((int)$post_id);

        if ( empty($schemas) ) {
            return;
        }

        echo "\n\n";
        foreach ( $schemas as $schema ) {
            if ( empty($schema) || ! is_array($schema) ) {
                continue;
            }

            echo '<script type="application/ld+json">';
            echo wp_json_encode($schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
            echo "</script>\n";
        }
        echo "\n\n";
    }

    /**
     * Збір усіх доступних схем для конкретного поста.
     */
    private function collect_schemas(int $post_id): array {
        $output = [];

        // 1. FAQ Schema
        $faq_data = get_post_meta($post_id, '_seojusai_faq_schema', true);
        if ( ! empty($faq_data) ) {
            $faq_array = is_string($faq_data) ? json_decode($faq_data, true) : $faq_data;
            if ( is_array($faq_array) ) {
                $output[] = [
                    '@context'   => 'https://schema.org',
                    '@type'      => 'FAQPage',
                    'mainEntity' => $faq_array,
                ];
            }
        }

        // 2. AI Suggested / LegalService / Contact Schema
        $keys = ['_seojusai_contact_schema', '_seojusai_ai_schema', '_seojusai_custom_schema'];
        foreach ( $keys as $key ) {
            $data = get_post_meta($post_id, $key, true);
            if ( ! empty($data) ) {
                $array = is_string($data) ? json_decode($data, true) : $data;
                if ( is_array($array) ) {
                    if ( ! isset($array['@context']) ) {
                        $array = array_merge(['@context' => 'https://schema.org'], $array);
                    }
                    $output[] = $array;
                }
            }
        }

        return $output;
    }
}
