<?php
declare(strict_types=1);

namespace SEOJusAI\AI\Engine;

defined('ABSPATH') || exit;

trait UtilsTrait {

    private static function fetch_rendered_html(string $url): string {
        $args = [
            'timeout'     => 25,
            'redirection' => 5,
            'headers'     => [
                'User-Agent' => 'SEOJusAI/1.0 (+https://jus.in.ua)',
                'Accept'     => 'text/html,application/xhtml+xml',
            ],
        ];

        $res = wp_remote_get($url, $args);
        if (is_wp_error($res)) return '';

        $code = (int) wp_remote_retrieve_response_code($res);
        if ($code < 200 || $code >= 300) return '';

        $html = (string) wp_remote_retrieve_body($res);
        return is_string($html) ? $html : '';
    }

    private static function mb_clip(string $text, int $limit): string {
        $text = trim($text);
        if ($text === '') return '';
        if (mb_strlen($text) <= $limit) return $text;
        return (string) mb_substr($text, 0, $limit);
    }

    private static function word_count(string $text): int {
        $text = trim($text);
        if ($text === '') return 0;
        $parts = preg_split('/\s+/u', $text);
        return is_array($parts) ? count($parts) : 0;
    }

    private static function compute_readability_ua_ru(string $text): float {
        $text = trim($text);
        if ($text === '') return 0.0;

        $sentences = preg_split('/[.!?]+/u', $text);
        $sentences = array_values(array_filter(array_map('trim', is_array($sentences) ? $sentences : [])));
        $sCount = max(1, count($sentences));

        $words = preg_split('/\s+/u', $text);
        $words = array_values(array_filter(array_map('trim', is_array($words) ? $words : [])));
        $wCount = max(1, count($words));

        $avgWordsPerSentence = $wCount / $sCount;

        $longWords = 0;
        foreach ($words as $w) {
            $w2 = preg_replace('/[^\p{L}\p{N}\-]+/u', '', $w);
            if ($w2 === '') continue;
            if (mb_strlen($w2) >= 9) $longWords++;
        }
        $longRate = $longWords / $wCount;

        $score = 100.0 - ($avgWordsPerSentence * 1.8) - ($longRate * 60.0);
        if ($score < 0) $score = 0.0;
        if ($score > 100) $score = 100.0;

        return round($score, 2);
    }

    private static function has_lsi_markers(string $text): bool {
        $t = mb_strtolower($text);
        return (bool) preg_match('/(суд|закон|кодекс|захист|позов|адвокат|кпк|кк|цк|ск|гпк|каc|верховн|рішення суду|постанова)/iu', $t);
    }

    private static function normalize_url(string $href): string {
        $href = trim($href);
        if ($href === '') return '';
        $href = html_entity_decode($href, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        return $href;
    }

    private static function is_probably_broken_href(string $href): bool {
        if ($href === '') return false;
        $h = strtolower($href);
        if (str_contains($h, 'undefined') || str_contains($h, 'null')) return true;
        if (preg_match('/\b404\b/u', $h)) return true;
        return false;
    }

    private static function safe_bool($v): bool {
        return (bool) $v;
    }
}
