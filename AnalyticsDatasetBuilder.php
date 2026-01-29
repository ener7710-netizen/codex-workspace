<?php
declare(strict_types=1);

namespace SEOJusAI\Analytics;

use SEOJusAI\GA4\Ga4Snapshot;

defined('ABSPATH') || exit;

/**
 * AnalyticsDatasetBuilder
 *
 * Будує компактний, об'єктивний датасет (GSC+GA4) для:
 * - передачі в Gemini (джерело реальності)
 * - передачі в OpenAI як фактичний контекст
 *
 * Принципи:
 * - беремо ТІЛЬКИ снапшоти (як джерело істини)
 * - обмежуємо розмір (top N)
 */
final class AnalyticsDatasetBuilder {

    /**
     * Побудувати компактний датасет для AI.
     *
     * @return array<string,mixed>
     */
    public static function build_for_ai(int $topPages = 30): array {

        $topPages = max(5, min(100, $topPages));

        $ga4 = Ga4Snapshot::latest();
        $gsc = self::latest_snapshot_by_type('gsc');

        $out = [
            'generated_at' => gmdate('c'),
            'ga4' => null,
            'gsc' => null,
            'merged' => null,
        ];

        if (is_array($ga4)) {
            $ga4Data = is_array($ga4['data'] ?? null) ? $ga4['data'] : [];
            $overview = is_array($ga4Data['overview'] ?? null) ? $ga4Data['overview'] : [];
            $pages = is_array($ga4Data['pages'] ?? null) ? $ga4Data['pages'] : [];

            // shrink pages
            $pages = array_slice($pages, 0, $topPages);
            $out['ga4'] = [
                'overview' => $overview,
                'top_pages' => $pages,
                '_snapshot' => $ga4['_snapshot'] ?? null,
            ];
        }

        if (is_array($gsc)) {
            $gscPayload = is_array($gsc['data'] ?? null) ? $gsc['data'] : $gsc;
            // Якщо GSC snapshot збережено як {site,data}, то rows можуть бути у data->data
            $rows = [];
            if (isset($gscPayload['data']) && is_array($gscPayload['data'])) {
                $rows = $gscPayload['data'];
            } elseif (isset($gscPayload['rows']) && is_array($gscPayload['rows'])) {
                $rows = $gscPayload['rows'];
            }
            if (is_array($rows)) {
                $rows = array_slice($rows, 0, $topPages);
            } else {
                $rows = [];
            }
            $out['gsc'] = [
                'rows_sample' => $rows,
                '_snapshot'   => $gsc['_snapshot'] ?? null,
            ];
        }

        // Best-effort merge by pagePath/url (very lightweight)
        $out['merged'] = self::best_effort_merge($out['ga4'], $out['gsc'], $topPages);

        return $out;
    }

    /**
     * @return array<string,mixed>|null
     */
    private static function latest_snapshot_by_type(string $type): ?array {
        global $wpdb;
        $table = $wpdb->prefix . 'seojusai_snapshots';
        $row = $wpdb->get_row(
            $wpdb->prepare("SELECT id, data_json, created_at FROM {$table} WHERE type = %s ORDER BY id DESC LIMIT 1", $type),
            ARRAY_A
        );
        if (!is_array($row) || empty($row['data_json'])) {
            return null;
        }
        $data = json_decode((string) $row['data_json'], true);
        if (!is_array($data)) {
            return null;
        }
        $data['_snapshot'] = [
            'id' => isset($row['id']) ? (int) $row['id'] : 0,
            'created_at' => (string) ($row['created_at'] ?? ''),
        ];
        return $data;
    }

    /**
     * @param array<string,mixed>|null $ga4
     * @param array<string,mixed>|null $gsc
     * @return array<string,mixed>|null
     */
    private static function best_effort_merge($ga4, $gsc, int $topPages): ?array {
        if (!is_array($ga4) || !is_array($gsc)) {
            return null;
        }

        $ga4Pages = is_array($ga4['top_pages'] ?? null) ? $ga4['top_pages'] : [];
        $gscRows  = is_array($gsc['rows_sample'] ?? null) ? $gsc['rows_sample'] : [];

        $map = [];
        foreach ($ga4Pages as $p) {
            if (!is_array($p)) continue;
            $path = (string) ($p['pagePath'] ?? '');
            if ($path === '') continue;
            $map[$path] = [
                'path' => $path,
                'ga4' => $p,
                'gsc' => null,
            ];
        }

        // GSC rows format: keys[page] or dimension values. We just attach raw row if it matches.
        foreach ($gscRows as $r) {
            if (!is_array($r)) continue;
            $path = '';
            if (isset($r['keys'][1])) {
                // default dims: query,page
                $path = (string) $r['keys'][1];
            } elseif (isset($r['keys'][0])) {
                $path = (string) $r['keys'][0];
            }
            if ($path === '') continue;
            $pathOnly = wp_parse_url($path, PHP_URL_PATH);
            $pathKey = is_string($pathOnly) && $pathOnly !== '' ? $pathOnly : $path;
            if (isset($map[$pathKey])) {
                $map[$pathKey]['gsc'] = $r;
            }
        }

        $merged = array_values($map);
        return [
            'rows' => array_slice($merged, 0, $topPages),
        ];
    }
}
