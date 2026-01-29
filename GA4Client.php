<?php
declare(strict_types=1);

namespace SEOJusAI\GA4;

use SEOJusAI\Core\EmergencyStop;

defined('ABSPATH') || exit;

/**
 * GA4Client
 *
 * Мінімальний клієнт Google Analytics Data API (v1beta) на Service Account.
 *
 * Принципи:
 * - тільки READ (runReport)
 * - best-effort: при помилках повертаємо порожні дані, не ламаючи UI/AI
 */
final class GA4Client {

    private const API_BASE = 'https://analyticsdata.googleapis.com/v1beta';

    /**
     * @return int|null numeric GA4 property ID, якщо задано
     */
    private function get_property_id(): ?int {
        $raw = (string) get_option('seojusai_ga4_property_id', '');
        $raw = trim($raw);
        if ($raw === '') {
            return null;
        }
        // allow "properties/123" or "123"
        if (strpos($raw, 'properties/') === 0) {
            $raw = substr($raw, strlen('properties/'));
        }
        $id = (int) preg_replace('/[^0-9]/', '', $raw);
        return $id > 0 ? $id : null;
    }

    public function is_connected(): bool {
        return Ga4ServiceAccount::is_connected() && (bool) $this->get_property_id();
    }

    /**
     * Overview метрики на рівні property.
     *
     * @return array<string,mixed>
     */
    public function get_overview(int $days = 30): array {
        $days = max(1, min(365, $days));
        $report = $this->run_report([
            'dateRanges' => [[
                'startDate' => $days . 'daysAgo',
                'endDate'   => 'today',
            ]],
            'dimensions' => [],
            'metrics'    => [
                ['name' => 'sessions'],
                ['name' => 'totalUsers'],
                ['name' => 'screenPageViews'],
                ['name' => 'engagementRate'],
                ['name' => 'averageSessionDuration'],
                ['name' => 'bounceRate'],
            ],
            'limit'      => 1,
        ]);

        $rows = $report['rows'] ?? [];
        if (!is_array($rows) || empty($rows[0]['metricValues'])) {
            return [];
        }

        $vals = $rows[0]['metricValues'];
        $get = static function(int $idx) use ($vals): float {
            $v = $vals[$idx]['value'] ?? '0';
            return is_numeric($v) ? (float) $v : 0.0;
        };

        return [
            'days'                 => $days,
            'sessions'             => (int) round($get(0)),
            'users'                => (int) round($get(1)),
            'pageviews'            => (int) round($get(2)),
            'engagementRate'       => $get(3),
            'avgSessionDuration'   => $get(4),
            'bounceRate'           => $get(5),
        ];
    }

    /**
     * Таблиця по сторінках (pagePath) з базовими метриками.
     *
     * @return array<int,array<string,mixed>>
     */
    public function get_pages(int $days = 30, int $limit = 200): array {
        $days  = max(1, min(365, $days));
        $limit = max(1, min(5000, $limit));

        $report = $this->run_report([
            'dateRanges' => [[
                'startDate' => $days . 'daysAgo',
                'endDate'   => 'today',
            ]],
            'dimensions' => [
                ['name' => 'pagePath'],
            ],
            'metrics' => [
                ['name' => 'sessions'],
                ['name' => 'totalUsers'],
                ['name' => 'screenPageViews'],
                ['name' => 'engagementRate'],
                ['name' => 'averageSessionDuration'],
                ['name' => 'bounceRate'],
            ],
            'orderBys' => [[
                'metric' => ['metricName' => 'sessions'],
                'desc'   => true,
            ]],
            'limit' => $limit,
        ]);

        $rows = $report['rows'] ?? [];
        if (!is_array($rows)) {
            return [];
        }

        $out = [];
        foreach ($rows as $row) {
            $dims = $row['dimensionValues'] ?? [];
            $m    = $row['metricValues'] ?? [];
            $path = isset($dims[0]['value']) ? (string) $dims[0]['value'] : '';
            if ($path === '') {
                continue;
            }
            $val = static function(int $idx) use ($m): float {
                $v = $m[$idx]['value'] ?? '0';
                return is_numeric($v) ? (float) $v : 0.0;
            };
            $out[] = [
                'pagePath'            => $path,
                'sessions'            => (int) round($val(0)),
                'users'               => (int) round($val(1)),
                'pageviews'           => (int) round($val(2)),
                'engagementRate'      => $val(3),
                'avgSessionDuration'  => $val(4),
                'bounceRate'          => $val(5),
            ];
        }

        return $out;
    }

    /**
     * Таблиця по сторінках з деталізацією.
     *
     * breakdown:
     * - country  -> dimensions: pagePath, country
     * - device   -> dimensions: pagePath, deviceCategory
     * - source   -> dimensions: pagePath, sessionSourceMedium
     *
     * @return array<int,array<string,mixed>>
     */
    public function get_pages_breakdown(int $days = 30, int $limit = 200, string $breakdown = 'country'): array {
        $days  = max(1, min(365, $days));
        $limit = max(1, min(5000, $limit));

        $breakdown = in_array($breakdown, ['country','device','source'], true) ? $breakdown : 'country';

        $dimName = 'country';
        $outKey  = 'country';
        if ($breakdown === 'device') {
            $dimName = 'deviceCategory';
            $outKey  = 'device';
        } elseif ($breakdown === 'source') {
            $dimName = 'sessionSourceMedium';
            $outKey  = 'source';
        }

        $report = $this->run_report([
            'dateRanges' => [[
                'startDate' => $days . 'daysAgo',
                'endDate'   => 'today',
            ]],
            'dimensions' => [
                ['name' => 'pagePath'],
                ['name' => $dimName],
            ],
            'metrics' => [
                ['name' => 'sessions'],
                ['name' => 'totalUsers'],
                ['name' => 'screenPageViews'],
                ['name' => 'engagementRate'],
                ['name' => 'averageSessionDuration'],
                ['name' => 'bounceRate'],
            ],
            'orderBys' => [[
                'metric' => ['metricName' => 'sessions'],
                'desc'   => true,
            ]],
            'limit' => $limit,
        ]);

        $rows = $report['rows'] ?? [];
        if (!is_array($rows)) {
            return [];
        }

        $out = [];
        foreach ($rows as $row) {
            $dims = $row['dimensionValues'] ?? [];
            $m    = $row['metricValues'] ?? [];
            $path = isset($dims[0]['value']) ? (string) $dims[0]['value'] : '';
            $dimv = isset($dims[1]['value']) ? (string) $dims[1]['value'] : '';
            if ($path === '') {
                continue;
            }
            $val = static function(int $idx) use ($m): float {
                $v = $m[$idx]['value'] ?? '0';
                return is_numeric($v) ? (float) $v : 0.0;
            };

            $out[] = [
                'pagePath'           => $path,
                $outKey              => $dimv,
                'sessions'           => (int) round($val(0)),
                'users'              => (int) round($val(1)),
                'pageviews'          => (int) round($val(2)),
                'engagementRate'     => $val(3),
                'avgSessionDuration' => $val(4),
                'bounceRate'         => $val(5),
            ];
        }

        return $out;
    }

    /**
     * Таймсерія по днях (для графіків).
     *
     * Повертає масив точок виду:
     *   [ ['date' => 'YYYY-MM-DD', 'sessions' => 10, 'users' => 8, 'pageviews' => 15 ], ... ]
     *
     * @return array<int,array<string,mixed>>
     */
    public function get_timeseries(int $days = 30): array {
        $days = max(1, min(365, $days));

        $report = $this->run_report([
            'dateRanges' => [[
                'startDate' => $days . 'daysAgo',
                'endDate'   => 'today',
            ]],
            'dimensions' => [
                ['name' => 'date'],
            ],
            'metrics' => [
                ['name' => 'sessions'],
                ['name' => 'totalUsers'],
                ['name' => 'screenPageViews'],
            ],
            'orderBys' => [[
                'dimension' => [ 'dimensionName' => 'date' ],
                'desc'      => false,
            ]],
            'limit' => $days,
        ]);

        $rows = $report['rows'] ?? [];
        if (!is_array($rows)) {
            return [];
        }

        $out = [];
        foreach ($rows as $row) {
            $dims = $row['dimensionValues'] ?? [];
            $m    = $row['metricValues'] ?? [];
            $rawDate = isset($dims[0]['value']) ? (string) $dims[0]['value'] : '';
            if ($rawDate === '') {
                continue;
            }
            // GA4 date format: YYYYMMDD.
            if (preg_match('/^(\d{4})(\d{2})(\d{2})$/', $rawDate, $mm)) {
                $date = $mm[1] . '-' . $mm[2] . '-' . $mm[3];
            } else {
                $date = $rawDate;
            }

            $val = static function(int $idx) use ($m): int {
                $v = $m[$idx]['value'] ?? '0';
                return is_numeric($v) ? (int) round((float) $v) : 0;
            };

            $out[] = [
                'date'     => $date,
                'sessions' => $val(0),
                'users'    => $val(1),
                'pageviews'=> $val(2),
            ];
        }

        return $out;
    }

    /**
     * Low-level runReport.
     *
     * @param array<string,mixed> $body
     * @return array<string,mixed>
     */
    public function run_report(array $body): array {
        if (EmergencyStop::is_active()) {
            return [];
        }

        $property_id = $this->get_property_id();
        if (!$property_id) {
            return [];
        }

        if (!Ga4ServiceAccount::is_connected()) {
            return [];
        }

        try {
            $token = Ga4TokenProvider::get_access_token();
        } catch (\Throwable $e) {
            return [];
        }

        $url = self::API_BASE . '/properties/' . $property_id . ':runReport';

        $response = wp_remote_post($url, [
            'timeout' => 30,
            'headers' => [
                'Authorization' => 'Bearer ' . $token,
                'Content-Type'  => 'application/json',
            ],
            'body' => wp_json_encode($body),
        ]);

        if (is_wp_error($response)) {
            return [];
        }

        $data = json_decode((string) wp_remote_retrieve_body($response), true);
        return is_array($data) ? $data : [];
    }
}
