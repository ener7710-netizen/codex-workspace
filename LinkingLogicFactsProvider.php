<?php
declare(strict_types=1);

namespace SEOJusAI\Analyze;

defined('ABSPATH') || exit;

/**
 * LinkingLogicFactsProvider
 *
 * Контекстна перелінковка для юридичних сайтів (STRICT MODE).
 *
 * [linking_logic] => [
 *   'article_to_service' => bool, // зі статті є лінк на сторінку послуги
 *   'service_to_cases'   => bool, // зі сторінки послуги є лінк на кейси / судові рішення
 *   'external_law_links' => int,  // кількість посилань на офіційні правові джерела
 * ]
 *
 * НЕ ВГАДУЄМО. Працюємо ТІЛЬКИ з HTML.
 */
final class LinkingLogicFactsProvider {

    /**
     * @param string $html      Повний HTML сторінки
     * @param string $page_type Тип сторінки: article | service | case | other
     */
    public static function extract(string $html, string $page_type): array {

        $links = self::extract_links($html);

        $article_to_service = false;
        $service_to_cases   = false;

        if ($page_type === 'article') {
            $article_to_service = self::has_service_link($links);
        }

        if ($page_type === 'service') {
            $service_to_cases = self::has_cases_link($links);
        }

        $external_law_links = self::count_external_law_links($links);

        return [
            'article_to_service' => $article_to_service,
            'service_to_cases'   => $service_to_cases,
            'external_law_links' => $external_law_links,
        ];
    }

    /* ============================================================
     * CORE LINK PARSER
     * ============================================================ */

    private static function extract_links(string $html): array {
        preg_match_all(
            '/<a\s[^>]*href=["\']([^"\']+)["\'][^>]*>(.*?)<\/a>/is',
            $html,
            $m
        );

        $links = [];

        if (!empty($m[1])) {
            foreach ($m[1] as $i => $href) {
                $links[] = [
                    'href' => trim((string)$href),
                    'text' => trim(wp_strip_all_tags((string)($m[2][$i] ?? ''))),
                ];
            }
        }

        return $links;
    }

    /* ============================================================
     * ARTICLE → SERVICE
     * ============================================================ */

    private static function has_service_link(array $links): bool {

        foreach ($links as $l) {
            $href = $l['href'];
            $text = mb_strtolower($l['text']);

            // типові slug-и сторінок послуг
            if (preg_match('/\/(poslugy|services|practice|practice-area|napryamy|advokat)/i', $href)) {
                return true;
            }

            // анкор як назва послуги
            if (preg_match('/(адвокат|юридичн|правов|захист|послуг)/iu', $text)) {
                return true;
            }
        }

        return false;
    }

    /* ============================================================
     * SERVICE → CASES
     * ============================================================ */

    private static function has_cases_link(array $links): bool {

        foreach ($links as $l) {
            $href = $l['href'];
            $text = mb_strtolower($l['text']);

            // кейси / судова практика
            if (preg_match('/\/(cases|case|praktyka|spravy|sudova-praktyka)/i', $href)) {
                return true;
            }

            if (preg_match('/(кейс|справа|судов|практик|рішення суду)/iu', $text)) {
                return true;
            }
        }

        return false;
    }

    /* ============================================================
     * EXTERNAL LAW LINKS
     * ============================================================ */

    private static function count_external_law_links(array $links): int {

        $count = 0;

        foreach ($links as $l) {
            $href = $l['href'];

            if (self::is_official_law_domain($href)) {
                $count++;
            }
        }

        return $count;
    }

    private static function is_official_law_domain(string $url): bool {

        // Українські офіційні та авторитетні правові ресурси
        $patterns = [
            'rada.gov.ua',
            'zakon.rada.gov.ua',
            'court.gov.ua',
            'reyestr.court.gov.ua',
            'supreme.court.gov.ua',
            'unba.org.ua',
            'erau.unba.org.ua',
            'ligazakon',
            'verdictum',
        ];

        foreach ($patterns as $p) {
            if (stripos($url, $p) !== false) {
                return true;
            }
        }

        return false;
    }
}
