<?php
declare(strict_types=1);

namespace SEOJusAI\PageActions;

use SEOJusAI\GA4\Ga4Snapshot;
use SEOJusAI\Snapshots\SnapshotRepository;

defined('ABSPATH') || exit;

/**
 * Збирає факти по одній сторінці (URL/path) для AI.
 *
 * Інваріанти:
 * - тільки читання (no writes)
 * - не викликає зовнішні API (тільки снапшоти + WP контент)
 */
final class PageInsightService {

    private SnapshotRepository $repo;

    public function __construct(?SnapshotRepository $repo = null) {
        $this->repo = $repo instanceof SnapshotRepository ? $repo : new SnapshotRepository();
    }

    /**
     * @return array<string,mixed>
     */
    public function build(string $urlOrPath): array {
        $path = self::normalize_path($urlOrPath);

        $out = [
            'generated_at' => gmdate('c'),
            'input' => $urlOrPath,
            'path' => $path,
            'wp' => [
                'post_id' => 0,
                'post_type' => null,
                'status' => null,
                'title' => null,
                'modified_gmt' => null,
                'word_count' => null,
            ],
            'metrics' => [
                'ga4' => null,
                'gsc' => null,
            ],
            'signals' => [
                'risk' => null,
            ],
            'snapshots' => [
                'ga4' => null,
                'gsc' => null,
            ],
        ];

        // WP context (best-effort)
        $postId = 0;
        if (function_exists('url_to_postid')) {
            $postId = (int) url_to_postid(home_url($path));
        }
        if ($postId > 0 && function_exists('get_post')) {
            $p = get_post($postId);
            if ($p) {
                $content = is_string($p->post_content) ? $p->post_content : '';
                $out['wp'] = [
                    'post_id' => $postId,
                    'post_type' => isset($p->post_type) ? (string) $p->post_type : null,
                    'status' => isset($p->post_status) ? (string) $p->post_status : null,
                    'title' => isset($p->post_title) ? (string) $p->post_title : null,
                    'modified_gmt' => isset($p->post_modified_gmt) ? (string) $p->post_modified_gmt : null,
                    'word_count' => $content !== '' ? self::word_count($content) : null,
                ];
            }
        }

        // GA4 snapshot lookup (best-effort)
        $ga4 = Ga4Snapshot::latest();
        if (is_array($ga4)) {
            $out['snapshots']['ga4'] = $ga4['_snapshot'] ?? null;
            $out['metrics']['ga4'] = self::find_ga4_for_path($ga4, $path);
        }

        // GSC snapshot lookup (best-effort)
        $gsc = $this->repo->get_latest_by_type('gsc');
        if (is_array($gsc)) {
            $out['snapshots']['gsc'] = $gsc['_snapshot'] ?? null;
            $out['metrics']['gsc'] = self::find_gsc_for_path($gsc, $path);
        }

        // Internal signal: risk (best-effort)
        if ($postId > 0) {
            $out['signals']['risk'] = self::get_avg_risk_for_entity($postId);
        }

        return $out;
    }

    private static function normalize_path(string $urlOrPath): string {
        $s = trim($urlOrPath);
        if ($s === '') {
            return '/';
        }
        // If it's a URL, extract path.
        if (preg_match('~^https?://~i', $s)) {
            $p = wp_parse_url($s, PHP_URL_PATH);
            if (is_string($p) && $p !== '') {
                $s = $p;
            } else {
                $s = '/';
            }
        }
        if ($s[0] !== '/') {
            $s = '/' . $s;
        }
        return $s;
    }

    private static function word_count(string $html): int {
        $text = wp_strip_all_tags($html);
        $text = trim($text);
        if ($text === '') {
            return 0;
        }
        // split by whitespace
        $parts = preg_split('/\s+/u', $text);
        return is_array($parts) ? count(array_filter($parts, static fn($v) => $v !== '')) : 0;
    }

    /**
     * @param array<string,mixed> $ga4Snap
     * @return array<string,mixed>|null
     */
    private static function find_ga4_for_path(array $ga4Snap, string $path): ?array {
        $pages = $ga4Snap['data']['pages'] ?? null;
        if (!is_array($pages)) {
            return null;
        }
        $cand = [$path];
        // try normalize trailing slash variants
        if ($path !== '/' && substr($path, -1) === '/') {
            $cand[] = rtrim($path, '/');
        } elseif ($path !== '/' && substr($path, -1) !== '/') {
            $cand[] = $path . '/';
        }
        foreach ($pages as $row) {
            if (!is_array($row)) {
                continue;
            }
            $pp = (string) ($row['pagePath'] ?? '');
            if ($pp === '') {
                continue;
            }
            foreach ($cand as $c) {
                if ($pp === $c) {
                    return $row;
                }
            }
        }
        return null;
    }

    /**
     * @param array<string,mixed> $gscSnap
     * @return array<string,mixed>|null
     */
    private static function find_gsc_for_path(array $gscSnap, string $path): ?array {
        $payload = is_array($gscSnap['data'] ?? null) ? $gscSnap['data'] : $gscSnap;

        $rows = null;
        if (isset($payload['data']) && is_array($payload['data'])) {
            $rows = $payload['data'];
        } elseif (isset($payload['rows']) && is_array($payload['rows'])) {
            $rows = $payload['rows'];
        }
        if (!is_array($rows)) {
            return null;
        }

        $cand = [$path];
        if ($path !== '/' && substr($path, -1) === '/') {
            $cand[] = rtrim($path, '/');
        } elseif ($path !== '/' && substr($path, -1) !== '/') {
            $cand[] = $path . '/';
        }

        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $keys = isset($row['keys']) && is_array($row['keys']) ? $row['keys'] : [];
            if (empty($keys)) {
                continue;
            }
            $url = (string) ($keys[0] ?? '');
            if ($url === '') {
                continue;
            }
            $p = wp_parse_url($url, PHP_URL_PATH);
            $rowPath = (is_string($p) && $p !== '') ? $p : $url;
            foreach ($cand as $c) {
                if ($rowPath === $c) {
                    return $row;
                }
            }
        }

        return null;
    }

    /**
     * @return array<string,mixed>|null
     */
    private static function get_avg_risk_for_entity(int $postId): ?array {
        global $wpdb;
        if (!isset($wpdb) || !is_object($wpdb)) {
            return null;
        }

        // Table name (prefix-aware)
        $table = $wpdb->prefix . 'seojusai_explanations';
        // risk_level is varchar; map to 0..4
        $sql = "SELECT COUNT(*) as cnt, AVG( CASE risk_level
                    WHEN 'low' THEN 1
                    WHEN 'medium' THEN 2
                    WHEN 'high' THEN 3
                    WHEN 'critical' THEN 4
                    ELSE 0
                END ) as avg_risk
                FROM {$table}
                WHERE entity_type IN ('post','page') AND entity_id = %d";

        try {
            $row = $wpdb->get_row($wpdb->prepare($sql, $postId), ARRAY_A);
            if (!is_array($row)) {
                return null;
            }
            return [
                'count' => (int) ($row['cnt'] ?? 0),
                'avg' => isset($row['avg_risk']) ? (float) $row['avg_risk'] : null,
            ];
        } catch (\Throwable $e) {
            return null;
        }
    }
}
