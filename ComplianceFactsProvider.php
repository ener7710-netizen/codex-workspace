<?php
declare(strict_types=1);

namespace SEOJusAI\Analyze;

use DOMDocument;
use DOMXPath;

defined('ABSPATH') || exit;

/**
 * ComplianceFactsProvider
 *
 * Для юридичних/YMYL сторінок: шукає елементи довіри та комплаєнсу:
 * - наявність контактів/адреси/телефону
 * - політики конфіденційності/умов
 * - дисклеймерів (інформативний характер, не є публічною офертою тощо)
 * - вихідні дані/автор/редакція
 *
 * Повертає факти, які далі використовують Rule Engine + AI.
 */
final class ComplianceFactsProvider {

    /**
     * Зібрати комплаєнс-факти зі сторінки поста (контент + шаблон).
     *
     * @return array<string,mixed>
     */
    public function build(int $post_id): array {
        $url = get_permalink($post_id);
        if (!$url) {
            return [
                'ok' => false,
                'reason' => 'no_url',
            ];
        }

        $html = $this->fetch_html($url);
        if ($html === '') {
            return [
                'ok' => false,
                'reason' => 'empty_html',
            ];
        }

        $text = $this->to_text($html);

        // Евристики (не "заглушки"): реальні перевірки по контенту/HTML.
        $has_phone = (bool) preg_match('/\+?\d[\d\s\-\(\)]{8,}\d/u', $text);
        $has_email = (bool) preg_match('/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/iu', $text);
        $has_address = (bool) preg_match('/\b(вул\.|вулиця|просп\.|проспект|буд\.|будинок|м\.|місто|область)\b/iu', $text);

        $has_privacy = $this->contains_any($text, [
            'політика конфіденційності', 'privacy policy', 'конфіденційності'
        ]);

        $has_terms = $this->contains_any($text, [
            'умови використання', 'terms of use', 'публічна оферта', 'умови надання послуг'
        ]);

        $has_disclaimer = $this->contains_any($text, [
            'інформація на сайті', 'не є юридичною консультацією', 'носить інформаційний характер',
            'результат залежить', 'не гарантує', 'публічною офертою не є'
        ]);

        $author_signals = [
            'has_author_block' => $this->has_author_block($html),
            'has_updated_date' => $this->contains_any($text, ['оновлено', 'updated', 'last updated', 'дата оновлення']),
        ];

        return [
            'ok' => true,
            'url' => $url,
            'contacts' => [
                'has_phone' => $has_phone,
                'has_email' => $has_email,
                'has_address' => $has_address,
                'score' => ($has_phone ? 1 : 0) + ($has_email ? 1 : 0) + ($has_address ? 1 : 0),
            ],
            'policies' => [
                'has_privacy' => $has_privacy,
                'has_terms' => $has_terms,
            ],
            'disclaimer' => [
                'has_disclaimer' => $has_disclaimer,
            ],
            'author' => $author_signals,
        ];
    }

    private function fetch_html(string $url): string {
        $resp = wp_remote_get($url, [
            'timeout' => 10,
            'redirection' => 3,
            'user-agent' => 'SEOJusAI/2026 (+WordPress)',
        ]);
        if (is_wp_error($resp)) {
            return '';
        }
        $code = (int) wp_remote_retrieve_response_code($resp);
        if ($code < 200 || $code >= 400) {
            return '';
        }
        $body = (string) wp_remote_retrieve_body($resp);
        return $body;
    }

    private function to_text(string $html): string {
        $html = preg_replace('/<script\b[^>]*>.*?<\/script>/si', ' ', $html) ?? $html;
        $html = preg_replace('/<style\b[^>]*>.*?<\/style>/si', ' ', $html) ?? $html;
        $text = wp_strip_all_tags($html);
        $text = preg_replace('/\s+/u', ' ', $text) ?? $text;
        return trim($text);
    }

    /** @param string[] $needles */
    private function contains_any(string $haystack, array $needles): bool {
        $h = mb_strtolower($haystack);
        foreach ($needles as $n) {
            if (mb_strpos($h, mb_strtolower($n)) !== false) {
                return true;
            }
        }
        return false;
    }

    private function has_author_block(string $html): bool {
        $dom = new DOMDocument();
        libxml_use_internal_errors(true);
        $dom->loadHTML($html);
        libxml_clear_errors();

        $xp = new DOMXPath($dom);
        // Евристики для типових тем: author, byline, schema author
        $nodes = $xp->query('//*[contains(@class,"author") or contains(@class,"byline") or @itemprop="author"]');
        return $nodes && $nodes->length > 0;
    }
}
