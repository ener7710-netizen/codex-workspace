<?php
declare(strict_types=1);

namespace SEOJusAI\Analyze;

defined('ABSPATH') || exit;

/**
 * SocialProofFactsProvider
 *
 * Deep social proof (STRICT MODE — тільки факти з HTML):
 *
 * [social_proof] => [
 *   'google_reviews_id'  => bool, // є реальний Google Place ID / maps cid / data-place-id / data-place
 *   'clutch_dou_links'   => bool, // є посилання на clutch.co або dou.ua
 *   'video_testimonials' => int,  // кількість відео-відгуків (iframe/youtube/vimeo + маркери "відгук")
 *   'reviews_block'      => bool, // на сторінці є блок відгуків (текстові маркери/класи)
 *   'rating_found'       => bool, // є рейтинг (4.8, 5/5, ⭐)
 *   'rating_value'       => string, // якщо знайдено (наприклад "4.8" або "5/5"), інакше ""
 * ]
 */
final class SocialProofFactsProvider {

    public static function extract(string $html): array {

        $text = self::clean_text($html);

        $google_reviews_id  = self::detect_google_reviews_id($html);
        $clutch_dou_links   = self::detect_clutch_dou_links($html);
        $video_testimonials = self::count_video_testimonials($html, $text);
        $reviews_block      = self::detect_reviews_block($html, $text);

        [$rating_found, $rating_value] = self::detect_rating($html, $text);

        return [
            'google_reviews_id'  => $google_reviews_id,
            'clutch_dou_links'   => $clutch_dou_links,
            'video_testimonials' => $video_testimonials,
            'reviews_block'      => $reviews_block,
            'rating_found'       => $rating_found,
            'rating_value'       => $rating_value,
        ];
    }

    /* ============================================================
     * Helpers
     * ============================================================ */

    private static function clean_text(string $html): string {
        $text = wp_strip_all_tags($html);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/\s+/u', ' ', (string)$text);
        return trim((string)$text);
    }

    /**
     * Google reviews binding:
     * - place_id=ChIJ...
     * - !1s0x... style place tokens
     * - cid=XXXXXXXX
     * - data-place-id / data-placeid / data-cid
     * - schema: aggregateRating + sameAs maps link (але тут лише факт наявності ID/прив'язки)
     */
    private static function detect_google_reviews_id(string $html): bool {

        // place_id
        if (preg_match('/place_id=ChI[J0-9A-Za-z_\-]{8,}/', $html)) {
            return true;
        }

        // cid= (часто у maps)
        if (preg_match('/[?&]cid=\d{6,}/', $html)) {
            return true;
        }

        // data атрибути
        if (preg_match('/data\-(place\-id|placeid|cid)=["\'][^"\']{6,}["\']/i', $html)) {
            return true;
        }

        // google maps embed з явними параметрами
        if (preg_match('/google\.com\/maps\/embed\?pb=/i', $html)) {
            // embed сам по собі не гарантує reviews, але це вже strong binding до Maps
            return true;
        }

        // посилання на maps з query та place tokens
        if (preg_match('/google\.(com|com\.ua)\/maps\/(place|search|dir)\/[^"\']+/i', $html)) {
            return true;
        }

        return false;
    }

    /**
     * Посилання на незалежні рейтинги:
     * - clutch.co
     * - dou.ua
     */
    private static function detect_clutch_dou_links(string $html): bool {
        return (bool) preg_match('/https?:\/\/(www\.)?(clutch\.co|dou\.ua)\b/i', $html);
    }

    /**
     * Відео-відгуки:
     * - iframe youtube/vimeo
     * - video tag
     * - поруч у тексті маркери "відгук", "testimonial", "review"
     *
     * STRICT: рахуємо тільки відео, які ймовірно є саме відгуками:
     * - або поруч є маркери, або контейнер має клас review/testimonial/відгук.
     */
    private static function count_video_testimonials(string $html, string $text): int {

        $count = 0;

        // 1) iframe youtube/vimeo
        preg_match_all('/<iframe[^>]+src=["\']([^"\']+)["\'][^>]*>/i', $html, $m);
        $iframes = $m[0] ?? [];
        $srcs    = $m[1] ?? [];

        foreach ($iframes as $i => $tag) {
            $src = (string) ($srcs[$i] ?? '');
            if ($src === '') continue;

            $is_video = (bool) preg_match('/(youtube\.com|youtu\.be|vimeo\.com)/i', $src);
            if (!$is_video) continue;

            // локальний контекст 400 символів навколо iframe
            $pos = mb_stripos($html, $tag);
            $ctx = '';
            if ($pos !== false) {
                $start = max(0, $pos - 400);
                $ctx = mb_substr($html, $start, 800);
            }

            if (self::context_looks_like_testimonial($ctx)) {
                $count++;
            }
        }

        // 2) <video> теги
        preg_match_all('/<video\b[^>]*>/i', $html, $vm);
        $videos = $vm[0] ?? [];

        foreach ($videos as $tag) {
            $pos = mb_stripos($html, $tag);
            $ctx = '';
            if ($pos !== false) {
                $start = max(0, $pos - 400);
                $ctx = mb_substr($html, $start, 800);
            }
            if (self::context_looks_like_testimonial($ctx)) {
                $count++;
            }
        }

        return $count;
    }

    private static function context_looks_like_testimonial(string $ctx_html): bool {
        $ctx_text = self::clean_text($ctx_html);

        // класи контейнерів/блоків
        if (preg_match('/(testimonial|review|reviews|відгук|відгуки|rating|stars)/iu', $ctx_html)) {
            return true;
        }

        // текстові маркери
        if (preg_match('/(відгук|відгуки|testimonial|review|клієнт(и)? кажуть|що кажуть)/iu', $ctx_text)) {
            return true;
        }

        return false;
    }

    /**
     * Блок відгуків (не обов'язково відео):
     * - текстові маркери
     * - типові класи: reviews/testimonials
     */
    private static function detect_reviews_block(string $html, string $text): bool {

        if (preg_match('/(testimonial|testimonials|reviews|review\-list|rating|stars)/i', $html)) {
            return true;
        }

        if (preg_match('/(відгук|відгуки|що кажуть наші клієнти|клієнти кажуть)/iu', $text)) {
            return true;
        }

        return false;
    }

    /**
     * Рейтинг:
     * - "4.8", "4,8"
     * - "5/5", "4.9/5"
     * - ⭐⭐⭐⭐
     */
    private static function detect_rating(string $html, string $text): array {

        // 5/5, 4.9/5
        if (preg_match('/\b([1-5](?:[.,]\d)?)\s*\/\s*5\b/u', $text, $m)) {
            return [true, (string)$m[0]];
        }

        // 4.8 (обмежимо 3.0-5.0 щоб не ловити випадкові числа)
        if (preg_match('/\b([3-5](?:[.,]\d{1,2})?)\b/u', $text, $m)) {
            $val = str_replace(',', '.', (string)$m[1]);
            $f = (float) $val;
            if ($f >= 3.0 && $f <= 5.0) {
                return [true, (string)$m[1]];
            }
        }

        // зірки
        if (mb_stripos($html, '⭐') !== false) {
            return [true, '⭐'];
        }

        // schema aggregateRating (факт)
        if (preg_match('/"aggregateRating"\s*:\s*{[^}]*"ratingValue"\s*:\s*"?([0-9.,]+)"?/i', $html, $m)) {
            return [true, (string)($m[1] ?? '')];
        }

        return [false, ''];
    }
}
