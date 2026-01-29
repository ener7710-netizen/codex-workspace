<?php
declare(strict_types=1);

namespace SEOJusAI\Analytics;

use SEOJusAI\GA4\GA4Client;
use SEOJusAI\GA4\Ga4Snapshot;
use SEOJusAI\GSC\GSCClient;

defined('ABSPATH') || exit;

/**
 * PagesMergeService
 *
 * Серверний merge сторінок GA4 + GSC з опційною деталізацією:
 * - country
 * - device
 * - source (GA4 sessionSourceMedium)
 *
 * Принципи:
 * - best-effort: якщо один із джерел недоступний, повертаємо те що є
 * - для базового режиму (breakdown=none) використовуємо снапшоти
 * - для breakdown режимів використовуємо live API + transient кеш
 */
final class PagesMergeService {

    /**
     * @param array{days:int,limit:int,site?:string,breakdown?:string} $args
     * @return array{rows:array<int,array<string,mixed>>, meta:array<string,mixed>}
     */
    public static function get_merged(array $args): array {
        $days = isset($args['days']) ? (int) $args['days'] : 30;
        $days = max(1, min(365, $days));

        $limit = isset($args['limit']) ? (int) $args['limit'] : 200;
        $limit = max(10, min(1000, $limit));

        $site = isset($args['site']) ? (string) $args['site'] : '';
        $breakdown = isset($args['breakdown']) ? (string) $args['breakdown'] : 'none';
        $breakdown = in_array($breakdown, ['none','country','device','source'], true) ? $breakdown : 'none';

        $cacheKey = 'seojusai_merged_pages_' . md5($days . '|' . $limit . '|' . $site . '|' . $breakdown);
        $cached = get_transient($cacheKey);
        if (is_array($cached) && isset($cached['rows'])) {
            $cached['meta']['source'] = 'cache';
            return $cached;
        }

        $ga4Rows = self::fetch_ga4($days, $limit, $breakdown);
        $gscRows = self::fetch_gsc($site, $days, $limit, $breakdown);

        $merged = self::merge($ga4Rows, $gscRows, $breakdown);

        $out = [
            'rows' => $merged,
            'meta' => [
                'days' => $days,
                'limit' => $limit,
                'site' => $site,
                'breakdown' => $breakdown,
                'ga4_count' => count($ga4Rows),
                'gsc_count' => count($gscRows),
                'source' => 'live',
            ],
        ];

        // cache кратко (чтобы UI был быстрый, но не устаревал надолго)
        set_transient($cacheKey, $out, 15 * MINUTE_IN_SECONDS);

        return $out;
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private static function fetch_ga4(int $days, int $limit, string $breakdown): array {
        // Use snapshots only for the base table (no breakdown).
        if ($breakdown === 'none') {
            $snap = Ga4Snapshot::latest();
            if (is_array($snap) && isset($snap['data']['pages']) && is_array($snap['data']['pages'])) {
                return array_slice($snap['data']['pages'], 0, $limit);
            }
        }

        $client = new GA4Client();
        if (!$client->is_connected()) {
            return [];
        }

        if ($breakdown === 'none') {
            return $client->get_pages($days, $limit);
        }

        return $client->get_pages_breakdown($days, $limit, $breakdown);
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private static function fetch_gsc(string $site, int $days, int $limit, string $breakdown): array {
        if ($site === '') {
            // best-effort: try first property
            $gsc = new GSCClient();
            $props = $gsc->list_properties();
            $site = is_array($props) && isset($props[0]) ? (string) $props[0] : '';
        }
        if ($site === '') {
            return [];
        }

        $gsc = new GSCClient();
        if (!$gsc->is_connected()) {
            return [];
        }

        $dims = ['page'];
        if ($breakdown === 'country') {
            $dims = ['page', 'country'];
        } elseif ($breakdown === 'device') {
            $dims = ['page', 'device'];
        }
        // source breakdown is GA4 only.

        $rows = $gsc->get_search_analytics($site, [
            'startDate'  => gmdate('Y-m-d', strtotime('-' . $days . ' days')),
            'endDate'    => gmdate('Y-m-d'),
            'dimensions' => $dims,
            'rowLimit'   => $limit,
        ]);

        return is_array($rows) ? $rows : [];
    }

    /**
     * @param array<int,array<string,mixed>> $ga4Rows
     * @param array<int,array<string,mixed>> $gscRows
     * @return array<int,array<string,mixed>>
     */
    private static function merge(array $ga4Rows, array $gscRows, string $breakdown): array {
        $map = [];

        // Index GA4 by composite key.
        foreach ($ga4Rows as $p) {
            if (!is_array($p)) {
                continue;
            }
            $path = isset($p['pagePath']) ? (string) $p['pagePath'] : '';
            if ($path === '') {
                continue;
            }

            $country = $breakdown === 'country' ? (string) ($p['country'] ?? '') : '';
            $device  = $breakdown === 'device' ? (string) ($p['device'] ?? '') : '';
            $source  = $breakdown === 'source' ? (string) ($p['source'] ?? '') : '';

            $key = self::key($path, $country, $device, $source);
            $map[$key] = [
                'path' => $path,
                'country' => $country,
                'device' => $device,
                'source' => $source,
                'ga4' => $p,
                'gsc' => null,
            ];
        }

        // Attach GSC.
        foreach ($gscRows as $r) {
            if (!is_array($r)) {
                continue;
            }
            $keys = isset($r['keys']) && is_array($r['keys']) ? $r['keys'] : [];
            if (empty($keys)) {
                continue;
            }

            $url = (string) ($keys[0] ?? '');
            if ($url === '') {
                continue;
            }

            $pathOnly = wp_parse_url($url, PHP_URL_PATH);
            $path = (is_string($pathOnly) && $pathOnly !== '') ? $pathOnly : $url;

            $country = '';
            $device  = '';
            $source  = '';

            if ($breakdown === 'country') {
                $country = isset($keys[1]) ? (string) $keys[1] : '';
            } elseif ($breakdown === 'device') {
                $device = isset($keys[1]) ? (string) $keys[1] : '';
            } elseif ($breakdown === 'source') {
                // GSC doesn't provide source; leave empty.
            }

            $key = self::key($path, $country, $device, $source);
            if (!isset($map[$key])) {
                $map[$key] = [
                    'path' => $path,
                    'country' => $country,
                    'device' => $device,
                    'source' => $source,
                    'ga4' => null,
                    'gsc' => $r,
                ];
            } else {
                $map[$key]['gsc'] = $r;
            }
        }

        $rows = array_values($map);

        // Default sort: ga4 sessions + gsc clicks.
        usort($rows, static function($a, $b): int {
            $as = (int) (is_array($a['ga4'] ?? null) ? ($a['ga4']['sessions'] ?? 0) : 0);
            $bs = (int) (is_array($b['ga4'] ?? null) ? ($b['ga4']['sessions'] ?? 0) : 0);
            $ac = (float) (is_array($a['gsc'] ?? null) ? ($a['gsc']['clicks'] ?? 0) : 0);
            $bc = (float) (is_array($b['gsc'] ?? null) ? ($b['gsc']['clicks'] ?? 0) : 0);
            $av = $as + $ac;
            $bv = $bs + $bc;
            return $bv <=> $av;
        });

        return $rows;
    }

    private static function key(string $path, string $country, string $device, string $source): string {
        return strtolower($path . '|' . $country . '|' . $device . '|' . $source);
    }
}
