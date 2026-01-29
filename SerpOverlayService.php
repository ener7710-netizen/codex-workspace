<?php
declare(strict_types=1);

namespace SEOJusAI\SERP;

defined('ABSPATH') || exit;

use SEOJusAI\Snapshots\SnapshotRepository;

/**
 * SerpOverlayService
 *
 * Сервісний шар: отримує SERP-дані (конкуренти) через SerpAPI та
 * фіксує результат у snapshot-таблиці для відтворюваності.
 *
 * Інваріанти:
 * - НЕ виконує AI-аналіз (Gemini/OpenAI) — це AI-шар.
 * - НЕ змінює контент і НЕ запускає Autopilot.
 */
final class SerpOverlayService {

    private SerpApiClient $client;
    private SnapshotRepository $repo;

    public function __construct(?SerpApiClient $client = null, ?SnapshotRepository $repo = null) {
        $this->client = $client ?? new SerpApiClient();
        $this->repo   = $repo   ?? new SnapshotRepository();
    }

    /**
     * @return array{ok:bool, error?:string, keyword?:string, params?:array<string,mixed>, snapshot_id?:int, fetched_at?:int, results?:array<int,array<string,mixed>>}
     */
    public function get_overlay(string $keyword, array $params = []): array {

        $keyword = trim($keyword);
        if ($keyword === '') {
            return ['ok' => false, 'error' => 'keyword_missing'];
        }

        if (!$this->client->is_ready()) {
            return ['ok' => false, 'error' => 'serp_not_configured'];
        }

        $limit = isset($params['limit']) ? (int) $params['limit'] : 10;
        $limit = min(10, max(1, $limit));

        $hl = isset($params['hl']) ? sanitize_key((string) $params['hl']) : 'uk';
        if ($hl === '') {
            $hl = 'uk';
        }

        $gl = isset($params['gl']) ? sanitize_key((string) $params['gl']) : 'ua';
        if ($gl === '') {
            $gl = 'ua';
        }

        $device = isset($params['device']) ? sanitize_key((string) $params['device']) : 'desktop';
        if (!in_array($device, ['desktop', 'mobile', 'tablet'], true)) {
            $device = 'desktop';
        }

        // Кеш 30 хв — щоб не палити ліміти SerpAPI при роботі в UI.
        $cache_key = 'seojusai_serp_overlay_' . md5($keyword . '|' . $hl . '|' . $gl . '|' . $device . '|' . $limit);
        $cached = get_transient($cache_key);
        if (is_array($cached) && isset($cached['ok'])) {
            return $cached;
        }

        $results = $this->client->fetch_google($keyword, $limit, $hl, $gl, $device);
        $fetched_at = time();

        $snapshot_id = 0;
        if (!empty($results)) {
            // entity_id — стабільний int ключ за keyword, щоб можна було діставати історію.
            $entity_id = (int) sprintf('%u', crc32(mb_strtolower($keyword)));
            $snapshot_id = $this->repo->insert('serp', $entity_id, [
                'kind'      => 'serp_overlay',
                'keyword'   => $keyword,
                'params'    => [
                    'hl' => $hl,
                    'gl' => $gl,
                    'device' => $device,
                    'limit' => $limit,
                ],
                'fetched_at' => $fetched_at,
                'results'   => $results,
            ]);
        }

        $payload = [
            'ok'         => true,
            'keyword'    => $keyword,
            'params'     => [ 'hl' => $hl, 'gl' => $gl, 'device' => $device, 'limit' => $limit ],
            'snapshot_id'=> $snapshot_id,
            'fetched_at' => $fetched_at,
            'results'    => $results,
        ];

        set_transient($cache_key, $payload, 30 * MINUTE_IN_SECONDS);
        return $payload;
    }
}
