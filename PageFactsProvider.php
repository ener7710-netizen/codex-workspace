<?php
declare(strict_types=1);

namespace SEOJusAI\Analyze;

defined('ABSPATH') || exit;

final class PageFactsProvider {

    /**
     * Основна точка входу
     */
    public static function get_by_url(string $url): array {
        $post_id = url_to_postid($url);
        if ($post_id <= 0) {
            return [];
        }

        $post = get_post($post_id);
        if (!$post) {
            return [];
        }

        // ===============================
        // 1️⃣ ПРАВИЛЬНИЙ HTML (Gutenberg)
        // ===============================
        $content_html = self::render_content_html($post);

        // ===============================
        // 2️⃣ ТЕКСТ (для regex / YMYL)
        // ===============================
        $text_content = trim(
            wp_strip_all_tags(
                html_entity_decode($content_html, ENT_QUOTES | ENT_HTML5)
            )
        );

        // ===============================
        // 3️⃣ ЗАГОЛОВКИ
        // ===============================
        $headings = self::extract_headings($content_html, (string) get_the_title($post_id));

        // ===============================
        // 4️⃣ META
        // ===============================
        $meta_desc = self::get_meta_description($post_id);

        // ===============================
        // 5️⃣ ЗОБРАЖЕННЯ
        // ===============================
        $images = self::analyze_images($content_html);

        // ===============================
        // 6️⃣ ПОСИЛАННЯ
        // ===============================
        $links = self::analyze_links($content_html);

        // ===============================
        // 7️⃣ SCHEMA (як є)
        // ===============================
        $schema_types = self::detect_schema_types($content_html, $post_id);

        return [
            'url'           => $url,
            'post_id'       => $post_id,
            'title'         => get_the_title($post_id),
            'content_html'  => $content_html,
            'text_content'  => $text_content,
            'headings'      => $headings,
            'meta_desc'     => $meta_desc,
            'images'        => $images,
            'links'         => $links,
            'schema_types'  => $schema_types,
            'word_count'    => str_word_count(mb_strtolower($text_content)),
        ];
    }

    /**
     * 🔥 КЛЮЧОВИЙ МЕТОД
     * Рендерить Gutenberg HTML правильно
     */
    private static function render_content_html(\WP_Post $post): string {
        $html = '';

        if (has_blocks($post->post_content)) {
            // ✅ ЕТАЛОН (як Rank Math)
            $html = do_blocks($post->post_content);
        } else {
            // fallback для класики
            $html = apply_filters('the_content', $post->post_content);
        }

        // фінальна очистка
        return trim((string) $html);
    }

    /**
     * Витягує H1–H6
     */
    private static function extract_headings(string $html, string $fallback_title = ''): array {
        preg_match_all('/<h([1-6])[^>]*>(.*?)<\/h\1>/is', $html, $m);

        $out = [];
        if (!empty($m[1])) {
            foreach ($m[1] as $i => $level) {
                $out[] = [
                    'level'  => (int) $level,
                    'text'   => trim(wp_strip_all_tags($m[2][$i])),
                    'source' => 'content',
                ];
            }
        }

        // Якщо H1 у контенті відсутній — вважаємо заголовок запису як H1 (типово для тем WP).
        $has_h1 = false;
        foreach ($out as $h) {
            if (($h['level'] ?? 0) === 1 && trim((string) ($h['text'] ?? '')) !== '') {
                $has_h1 = true;
                break;
            }
        }

        if (!$has_h1 && $fallback_title !== '') {
            $out[] = [
                'level'  => 1,
                'text'   => trim($fallback_title),
                'source' => 'title',
            ];
        }

        return $out;
    }

    /**
     * META DESCRIPTION (Rank Math / Yoast / WP)
     */
    private static function get_meta_description(int $post_id): string {
        // Rank Math
        $rm = get_post_meta($post_id, 'rank_math_description', true);
        if ($rm) return (string) $rm;

        // Yoast
        $yoast = get_post_meta($post_id, '_yoast_wpseo_metadesc', true);
        if ($yoast) return (string) $yoast;

        // WP excerpt
        $post = get_post($post_id);
        if ($post && $post->post_excerpt) {
            return trim((string) $post->post_excerpt);
        }

        return '';
    }

    /**
     * Аналіз зображень + ALT
     */
    private static function analyze_images(string $html): array {
        preg_match_all('/<img[^>]+>/i', $html, $imgs);

        $total = 0;
        $missing_alt = 0;

        foreach ($imgs[0] ?? [] as $img) {
            $total++;

            if (
                !preg_match('/alt\s*=\s*["\']([^"\']+)["\']/i', $img)
                || preg_match('/alt\s*=\s*["\']\s*["\']/i', $img)
            ) {
                $missing_alt++;
            }
        }

        return [
            'total'       => $total,
            'missing_alt' => $missing_alt,
        ];
    }

    /**
     * Аналіз посилань
     */
    private static function analyze_links(string $html): array {
        preg_match_all('/<a\s+[^>]*href=["\']([^"\']+)["\']/i', $html, $m);

        $internal = 0;
        $external = 0;
        $host = wp_parse_url(home_url(), PHP_URL_HOST);

        foreach ($m[1] ?? [] as $href) {
            if (str_starts_with($href, '/')) {
                $internal++;
            } else {
                $link_host = wp_parse_url($href, PHP_URL_HOST);
                if ($link_host && $link_host !== $host) {
                    $external++;
                } else {
                    $internal++;
                }
            }
        }

        return [
            'internal' => $internal,
            'external' => $external,
        ];
    }

    /**
     * Базове визначення schema
     */
    private static function detect_schema_types(string $html = '', int $post_id = 0): array {
        $types = [];

        // 1) JSON-LD у HTML (може бути від теми, інших плагінів або SEOJusAI)
        if ($html !== '') {
            preg_match_all('/<script[^>]+type=["\']application\/ld\+json["\'][^>]*>(.*?)<\/script>/is', $html, $m);
            foreach (($m[1] ?? []) as $json) {
                $json = trim((string) $json);
                if ($json === '') continue;

                $data = json_decode($json, true);
                if (!is_array($data)) continue;

                // може бути @graph
                $candidates = [];
                if (isset($data['@graph']) && is_array($data['@graph'])) {
                    $candidates = $data['@graph'];
                } else {
                    $candidates = [$data];
                }

                foreach ($candidates as $item) {
                    if (!is_array($item)) continue;
                    $t = $item['@type'] ?? null;
                    if (is_string($t) && $t !== '') {
                        $types[] = $t;
                    } elseif (is_array($t)) {
                        foreach ($t as $tt) {
                            if (is_string($tt) && $tt !== '') $types[] = $tt;
                        }
                    }
                }
            }
        }

        // 2) Маркери інших SEO‑плагінів (щоб розуміти конфлікти/дублі)
        if (function_exists('rank_math')) {
            $types[] = 'RankMath';
        }
        if (defined('WPSEO_VERSION')) {
            $types[] = 'Yoast';
        }
        if (defined('AIOSEO_VERSION')) {
            $types[] = 'AIOSEO';
        }

        // 3) Маркер SEOJusAI (якщо у нас є збережене рішення/аплай)
        if ($post_id > 0) {
            $applied = get_post_meta($post_id, '_seojusai_schema_applied', true);
            if (!empty($applied)) {
                $types[] = 'SEOJusAI';
            }
        }

        $types = array_values(array_unique(array_filter($types)));

        return $types;
    }

        return $types;
    }
}
