<?php
declare(strict_types=1);

namespace SEOJusAI\Analyze;

use SEOJusAI\Crawl\HtmlSnapshot;

defined('ABSPATH') || exit;

/**
 * PageAuditSummary
 * Builds fast, UI-friendly issue list + counters for a post.
 *
 * This is used for:
 *  - list-table column badge (Pages/Posts)
 *  - Gutenberg sidebar summary
 *
 * IMPORTANT:
 *  - prefers front HTML snapshot (header/footer/popups) to avoid "editor blind spots".
 */
final class PageAuditSummary {

    public const META_KEY = '_seojusai_audit_summary';

    public static function compute(int $post_id, bool $force_snapshot = false): array {
        $post = get_post($post_id);
        if (!$post) {
            return ['ok' => false, 'error' => 'post_not_found'];
        }

        // 1) WP content facts (Gutenberg)
        $facts = PageFactsProvider::get_by_url((string) get_permalink($post_id));

        // 2) Prefer front snapshot HTML (captures H1/schema/phone/address from theme/header/footer/popup markup)
        $front_html = '';
        $snap = null;
        if (class_exists(HtmlSnapshot::class)) {
            $snap = HtmlSnapshot::refresh_for_post($post_id, $force_snapshot);
            if (!$snap) {
                $snap = HtmlSnapshot::load_for_post($post_id);
            }
            if ($snap) {
                $front_html = (string) $snap->get_html();
            }
        }

        // Build issues preferring front_html when available.
        $headings = $facts['headings'] ?? [];
        if ($front_html !== '') {
            $headings = self::extract_headings_from_html($front_html, (string) get_the_title($post_id));
        }

        $has_h1 = self::has_h1($headings);
        $schema_types = $facts['schema_types'] ?? [];
        if ($front_html !== '') {
            $schema_types = self::detect_schema_types_from_html($front_html);
        }

        $contact = ($front_html !== '')
            ? self::detect_contact_from_html($front_html)
            : ['phones' => 0, 'addresses' => 0];

        $issues = [];
        // Critical: no H1 (even after front snapshot)
        if (!$has_h1) {
            $issues[] = ['level' => 'critical', 'code' => 'missing_h1', 'message' => 'Відсутній H1 на фронті'];
        }
        // Warning: no schema at all
        if (empty($schema_types)) {
            $issues[] = ['level' => 'warning', 'code' => 'missing_schema', 'message' => 'Schema (JSON-LD) не знайдено'];
        }
        // Info: no phone/address detected (useful for service pages)
        if (($contact['phones'] ?? 0) === 0) {
            $issues[] = ['level' => 'info', 'code' => 'missing_phone', 'message' => 'Телефон на сторінці не знайдено'];
        }
        if (($contact['addresses'] ?? 0) === 0) {
            $issues[] = ['level' => 'info', 'code' => 'missing_address', 'message' => 'Адреса на сторінці не знайдена'];
        }

        // Images ALT warnings
        $missing_alt = (int) (($facts['images']['missing_alt'] ?? 0));
        if ($missing_alt > 0) {
            $issues[] = ['level' => 'warning', 'code' => 'missing_alt', 'message' => 'Є зображення без ALT: ' . $missing_alt];
        }

        $counts = self::count_levels($issues);

        return [
            'ok'        => true,
            'post_id'   => $post_id,
            'url'       => (string) get_permalink($post_id),
            'score'     => self::compute_score($issues, [
                'headings'     => $headings,
                'schema_types' => $schema_types,
                'contact'      => $contact,
            ]),
            'counts'    => $counts,
            'issues'    => $issues,
            'facts'     => [
                'headings'     => $headings,
                'schema_types' => $schema_types,
                'contact'      => $contact,
                'snapshot_at'  => $snap ? $snap->get_captured_at() : 0,
            ],
            'updated_at'=> time(),
        ];
    }


    private static function compute_score(array $issues, array $facts): int {
        $score = 100;

        $critical = 0;
        $warning  = 0;
        $info     = 0;

        foreach ($issues as $i) {
            $lvl = (string) ($i['level'] ?? '');
            if ($lvl === 'critical') { $critical++; }
            elseif ($lvl === 'warning') { $warning++; }
            elseif ($lvl === 'info') { $info++; }
        }

        // Penalties
        $score -= ($critical * 25);
        $score -= ($warning * 10);
        $score -= ($info * 3);

        // Bonuses/adjustments from facts (lightweight, deterministic)
        $schema_types = $facts['schema_types'] ?? [];
        if (is_array($schema_types) && !empty($schema_types)) {
            $score += 5;
        }

        $contact = $facts['contact'] ?? [];
        $phones = (int) ($contact['phones'] ?? 0);
        $addresses = (int) ($contact['addresses'] ?? 0);
        if ($phones > 0) { $score += 2; }
        if ($addresses > 0) { $score += 2; }

        $headings = $facts['headings'] ?? [];
        if (is_array($headings)) {
            $h2plus = 0;
            foreach ($headings as $h) {
                $lvl = (int) ($h['level'] ?? 0);
                if ($lvl >= 2 && $lvl <= 6) { $h2plus++; }
            }
            if ($h2plus >= 3) { $score += 3; }
            elseif ($h2plus >= 1) { $score += 1; }
        }

        if ($score < 0) { $score = 0; }
        if ($score > 100) { $score = 100; }

        return (int) $score;
    }

    public static function store(int $post_id, array $summary): void {
        update_post_meta($post_id, self::META_KEY, wp_json_encode($summary, JSON_UNESCAPED_UNICODE));

        // Дублікат ключових метрик для UI (лістинг/редактор).
        $score = isset($summary['score']) ? (int) $summary['score'] : null;
        if ($score !== null) {
            if ($score < 0) { $score = 0; }
            if ($score > 100) { $score = 100; }
            update_post_meta($post_id, '_seojusai_score', $score);
            update_post_meta($post_id, '_seojusai_score_updated', (string) time());
        }
    }

    public static function load(int $post_id): ?array {
        $raw = (string) get_post_meta($post_id, self::META_KEY, true);
        if ($raw === '') {
            return null;
        }
        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : null;
    }

    private static function count_levels(array $issues): array {
        $c = ['critical' => 0, 'warning' => 0, 'info' => 0];
        foreach ($issues as $i) {
            $lvl = (string) ($i['level'] ?? '');
            if (isset($c[$lvl])) {
                $c[$lvl]++;
            }
        }
        $c['total'] = $c['critical'] + $c['warning'] + $c['info'];
        return $c;
    }

    private static function extract_headings_from_html(string $html, string $fallback_title = ''): array {
        preg_match_all('/<h([1-6])[^>]*>(.*?)<\/h\1>/is', $html, $m);
        $out = [];
        if (!empty($m[1])) {
            foreach ($m[1] as $idx => $level) {
                $out[] = [
                    'level'  => (int) $level,
                    'text'   => trim(wp_strip_all_tags($m[2][$idx] ?? '')),
                    'source' => 'front',
                ];
            }
        }
        // If no H1 on front, keep fallback title as probable theme H1.
        if (!self::has_h1($out) && $fallback_title !== '') {
            $out[] = ['level' => 1, 'text' => trim($fallback_title), 'source' => 'title'];
        }
        return $out;
    }

    private static function has_h1(array $headings): bool {
        foreach ($headings as $h) {
            if (((int) ($h['level'] ?? 0)) === 1 && trim((string) ($h['text'] ?? '')) !== '') {
                return true;
            }
        }
        return false;
    }

    private static function detect_schema_types_from_html(string $html): array {
        $types = [];
        preg_match_all('/<script[^>]+type=["\']application\/ld\+json["\'][^>]*>(.*?)<\/script>/is', $html, $m);
        foreach (($m[1] ?? []) as $json) {
            $json = trim((string) $json);
            if ($json === '') {
                continue;
            }
            $decoded = json_decode($json, true);
            if (!$decoded) {
                continue;
            }
            $types = array_merge($types, self::extract_schema_types($decoded));
        }
        $types = array_values(array_unique(array_filter($types)));
        sort($types);
        return $types;
    }

    private static function extract_schema_types($node): array {
        $types = [];
        if (is_array($node)) {
            if (isset($node['@type'])) {
                if (is_string($node['@type'])) {
                    $types[] = $node['@type'];
                } elseif (is_array($node['@type'])) {
                    foreach ($node['@type'] as $t) {
                        if (is_string($t)) $types[] = $t;
                    }
                }
            }
            foreach ($node as $v) {
                $types = array_merge($types, self::extract_schema_types($v));
            }
        }
        return $types;
    }

    private static function detect_contact_from_html(string $html): array {
        $phones = 0;
        $addresses = 0;

        // Phone: tel: links and common UA formats
        preg_match_all('/href=["\']tel:([^"\']+)["\']/i', $html, $m);
        $phones += count($m[1] ?? []);
        if ($phones === 0) {
            // fallback regex for +380...
            if (preg_match_all('/\+?380\s*\(?\d{2}\)?\s*\d{3}[\s\-]?\d{2}[\s\-]?\d{2}/', $html, $m2)) {
                $phones += count($m2[0] ?? []);
            }
        }

        // Address: schema PostalAddress or microdata itemtype, plus common UA markers
        if (stripos($html, 'PostalAddress') !== false || stripos($html, 'streetAddress') !== false) {
            $addresses++;
        } else {
            if (preg_match('/(вул\.|улиц|просп\.|бульвар|пл\.|місто|м\.)/iu', $html)) {
                $addresses++;
            }
        }

        return ['phones' => $phones, 'addresses' => $addresses];
    }
}
