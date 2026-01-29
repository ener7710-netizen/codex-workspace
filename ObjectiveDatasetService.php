<?php
declare(strict_types=1);

namespace SEOJusAI\Analytics;

use SEOJusAI\GA4\Ga4Snapshot;
use SEOJusAI\Snapshots\SnapshotRepository;

defined('ABSPATH') || exit;

/**
 * ObjectiveDatasetService
 *
 * Логіка цього компонента належить до шару Application/Service.
 * Він будує компактний, відтворюваний датасет для AI на основі даних,
 * що вже зафіксовані у snapshot таблиці.
 *
 * Інваріанти:
 * - не робить HTTP-запитів до зовнішніх API (тільки снапшоти)
 * - не виконує жодних дій/рішень (тільки збір фактів)
 * - не записує опції/налаштування (read-only)
 */
final class ObjectiveDatasetService {

    private SnapshotRepository $repo;

    public function __construct(?SnapshotRepository $repo = null) {
        $this->repo = $repo instanceof SnapshotRepository ? $repo : new SnapshotRepository();
    }

    /**
     * Побудувати датасет для Gemini/OpenAI.
     *
     * @return array<string,mixed>
     */
    public function build(int $topRows = 30): array {
        $topRows = max(5, min(200, $topRows));

        $out = [
            'generated_at' => gmdate('c'),
            'snapshots' => [
                'ga4' => null,
                'gsc' => null,
            ],
            'overview' => [
                'ga4' => null,
                'gsc' => null,
            ],
            // Основна цінність: merged pages (GA4 + GSC) у вигляді фактів.
            'merged_pages' => [
                'rows' => [],
                'meta' => [ 'limit' => $topRows ],
            ],
        ];

        // GA4: уже має snapshot meta через Ga4Snapshot::latest().
        $ga4 = Ga4Snapshot::latest();
        if (is_array($ga4)) {
            $ga4Data = is_array($ga4['data'] ?? null) ? $ga4['data'] : [];
            $out['snapshots']['ga4'] = $ga4['_snapshot'] ?? null;
            $out['overview']['ga4'] = is_array($ga4Data['overview'] ?? null) ? $ga4Data['overview'] : null;
        }

        // GSC: беремо останній snapshot з БД.
        $gsc = $this->repo->get_latest_by_type('gsc');
        if (is_array($gsc)) {
            $out['snapshots']['gsc'] = $gsc['_snapshot'] ?? null;
            // Для overview залишаємо компактний зразок: сумарні/агреговані показники, якщо вони є.
            $payload = is_array($gsc['data'] ?? null) ? $gsc['data'] : $gsc;
            $out['overview']['gsc'] = isset($payload['overview']) && is_array($payload['overview']) ? $payload['overview'] : null;
        }

        $out['merged_pages'] = $this->merge_pages_from_snapshots($ga4, $gsc, $topRows);

        return $out;
    }

    /**
     * @param array<string,mixed>|null $ga4
     * @param array<string,mixed>|null $gsc
     * @return array{rows:array<int,array<string,mixed>>, meta:array<string,mixed>}
     */
    private function merge_pages_from_snapshots($ga4, $gsc, int $topRows): array {

        $ga4Snap = is_array($ga4) ? $ga4 : null;
        $gscSnap = is_array($gsc) ? $gsc : null;

        $ga4Pages = [];
        if ($ga4Snap && isset($ga4Snap['data']['pages']) && is_array($ga4Snap['data']['pages'])) {
            $ga4Pages = $ga4Snap['data']['pages'];
        }

        // GSC rows можуть лежати у різних ключах залежно від формату.
        $gscRows = [];
        if ($gscSnap) {
            $payload = is_array($gscSnap['data'] ?? null) ? $gscSnap['data'] : $gscSnap;
            if (isset($payload['data']) && is_array($payload['data'])) {
                $gscRows = $payload['data'];
            } elseif (isset($payload['rows']) && is_array($payload['rows'])) {
                $gscRows = $payload['rows'];
            }
        }

        $ga4Id = (int) (($ga4Snap['_snapshot']['id'] ?? 0));
        $gscId = (int) (($gscSnap['_snapshot']['id'] ?? 0));

        // Індексація GA4.
        $map = [];
        foreach ($ga4Pages as $p) {
            if (!is_array($p)) { continue; }
            $path = (string) ($p['pagePath'] ?? '');
            if ($path === '') { continue; }
            $key = strtolower($path);
            $map[$key] = [
                'path' => $path,
                'ga4'  => $p,
                'gsc'  => null,
            ];
        }

        // Приєднання GSC.
        foreach ($gscRows as $r) {
            if (!is_array($r)) { continue; }
            $keys = isset($r['keys']) && is_array($r['keys']) ? $r['keys'] : [];
            if (empty($keys)) { continue; }
            $url = (string) ($keys[0] ?? '');
            if ($url === '') { continue; }

            $pathOnly = wp_parse_url($url, PHP_URL_PATH);
            $path = (is_string($pathOnly) && $pathOnly !== '') ? $pathOnly : $url;
            $key = strtolower($path);

            if (!isset($map[$key])) {
                $map[$key] = [
                    'path' => $path,
                    'ga4'  => null,
                    'gsc'  => $r,
                ];
            } else {
                $map[$key]['gsc'] = $r;
            }
        }

        $rows = array_values($map);

        // Стабільне сортування: GA4 sessions + GSC clicks.
        usort($rows, static function($a, $b): int {
            $as = (int) (is_array($a['ga4'] ?? null) ? ($a['ga4']['sessions'] ?? 0) : 0);
            $bs = (int) (is_array($b['ga4'] ?? null) ? ($b['ga4']['sessions'] ?? 0) : 0);
            $ac = (float) (is_array($a['gsc'] ?? null) ? ($a['gsc']['clicks'] ?? 0) : 0);
            $bc = (float) (is_array($b['gsc'] ?? null) ? ($b['gsc']['clicks'] ?? 0) : 0);
            return ($bs + $bc) <=> ($as + $ac);
        });

        $rows = array_slice($rows, 0, $topRows);

        return [
            'rows' => $rows,
            'meta' => [
                'limit' => $topRows,
                'snapshots' => [ 'ga4' => $ga4Id, 'gsc' => $gscId ],
                'source' => 'snapshots_only',
            ],
        ];
    }
}
