<?php
declare(strict_types=1);

namespace SEOJusAI\Competitive;

defined('ABSPATH') || exit;

/**
 * CompetitiveScanner
 *
 * Сканує список URL конкурентів та витягує тільки сигнали:
 * - тип сторінки (problem/info/unknown)
 * - наявність soft CTA (tel/contact/консультація) та приблизна позиція.
 *
 * ВАЖЛИВО: ми НЕ зберігаємо HTML конкурентів і НЕ копіюємо тексти.
 */
final class CompetitiveScanner {

    private CompetitiveRepository $repo;

    public function __construct(?CompetitiveRepository $repo = null) {
        $this->repo = $repo ?? new CompetitiveRepository();
    }

    /**
     * @param array<int,string> $urls
     */
    public function scan_competitor(int $competitor_id, array $urls): void {
        $ok = false;
        foreach ($urls as $url) {
            $url = esc_url_raw((string) $url);
            if (!$url) {
                continue;
            }

            $sig = $this->scan_url($url);
            $this->repo->upsert_signal(
                $competitor_id,
                $url,
                $sig['page_type'],
                (bool) $sig['has_soft_cta'],
                $sig['cta_position']
            );
            $ok = true;
        }

        $this->repo->mark_scanned($competitor_id, $ok ? 'ok' : 'empty');
    }

    /**
     * @return array{page_type:string,has_soft_cta:bool,cta_position:string}
     */
    public function scan_url(string $url): array {
        $resp = wp_remote_get($url, [
            'timeout' => 8,
            'redirection' => 2,
            'headers' => [
                'User-Agent' => 'SEOJusAI/2.3 (MarketSignals)',
                'Accept' => 'text/html,application/xhtml+xml',
            ],
        ]);

        if (is_wp_error($resp)) {
            return ['page_type' => $this->guess_page_type($url, ''), 'has_soft_cta' => false, 'cta_position' => 'none'];
        }

        $code = (int) wp_remote_retrieve_response_code($resp);
        if ($code < 200 || $code >= 300) {
            return ['page_type' => $this->guess_page_type($url, ''), 'has_soft_cta' => false, 'cta_position' => 'none'];
        }

        $body = (string) wp_remote_retrieve_body($resp);
        // Remove scripts/styles to reduce false positives
        $clean = preg_replace('~<script[^>]*>.*?</script>~is', ' ', $body);
        $clean = preg_replace('~<style[^>]*>.*?</style>~is', ' ', (string) $clean);
        $text  = wp_strip_all_tags((string) $clean);
        $hay   = mb_strtolower($text);

        $page_type = $this->guess_page_type($url, $hay);
        $cta = $this->detect_soft_cta($body, $hay);

        return [
            'page_type' => $page_type,
            'has_soft_cta' => $cta['has'],
            'cta_position' => $cta['pos'],
        ];
    }

    private function guess_page_type(string $url, string $hay): string {
        $u = mb_strtolower($url);
        // Problem-scenario patterns (UA/RU/EN minimal)
        $problem = [
            'що робити', 'что делать', 'як діяти', 'як діяти', 'обшук', 'затрим', 'арешт', 'підозр', 'повістк',
            'уголов', 'кримін', 'tax', 'подат', 'перевірк',
        ];
        foreach ($problem as $k) {
            if ($k && (str_contains($u, $k) || ($hay && str_contains($hay, $k)))) {
                return 'problem';
            }
        }

        // Informational patterns
        $info = ['закон', 'кодекс', 'стаття', 'article', 'definition', 'визначення', 'довід', 'faq', 'питання'];
        foreach ($info as $k) {
            if ($k && (str_contains($u, $k) || ($hay && str_contains($hay, $k)))) {
                return 'info';
            }
        }

        return 'unknown';
    }

    /**
     * @return array{has:bool,pos:string}
     */
    private function detect_soft_cta(string $html, string $hay): array {
        $has = false;
        $first = -1;
        $len = max(1, strlen($hay));

        // Tel links / contact links are strong soft CTA signals
        if (preg_match('~href=["\']tel:~i', $html, $m, PREG_OFFSET_CAPTURE)) {
            $has = true;
            $first = (int) ($m[0][1] ?? 0);
        }
        if (!$has && preg_match('~href=["\'][^"\']*(contact|kontakty|контакти|контакты)[^"\']*["\']~iu', $html, $m, PREG_OFFSET_CAPTURE)) {
            $has = true;
            $first = (int) ($m[0][1] ?? 0);
        }

        // Soft language (not promises)
        if (!$has) {
            $patterns = [
                'отримати консультац',
                'отримати правову оцінк',
                'правова оцінка',
                'зверніться',
                'звернутися',
                'зв\’язатися',
                'зв\'язатися',
                'консультац',
                'contact us',
            ];
            foreach ($patterns as $p) {
                $pos = mb_strpos($hay, $p);
                if ($pos !== false) {
                    $has = true;
                    $first = (int) $pos;
                    break;
                }
            }
        }

        if (!$has) {
            return ['has' => false, 'pos' => 'none'];
        }

        $ratio = $first <= 0 ? 0.1 : ($first / $len);
        $pos = 'middle';
        if ($ratio < 0.33) $pos = 'top';
        elseif ($ratio > 0.66) $pos = 'bottom';

        return ['has' => true, 'pos' => $pos];
    }
}
