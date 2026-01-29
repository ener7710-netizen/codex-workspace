<?php
declare(strict_types=1);

namespace SEOJusAI\Competitive;

use SEOJusAI\SERP\SerpClient;
use SEOJusAI\Snapshots\SnapshotRepository;

defined('ABSPATH') || exit;

final class MarketRefresher {

    private CompetitiveRepository $repo;
    private SerpClient $serp;

    public function __construct(CompetitiveRepository $repo) {
        $this->repo = $repo;
        $this->serp = new SerpClient();
    }

    /**
     * Повний цикл: SERP -> конкуренти -> сигнали -> ринкові правила.
     * @return array{ok:bool, queries:int, competitors:int, signals:int, rules:array<string,mixed>}
     */
    public function refresh(int $max_queries = 8, int $serp_limit = 10, int $urls_per_competitor = 5): array {
        $queries = $this->get_market_queries($max_queries);
        if (empty($queries)) {
            return ['ok' => false, 'queries' => 0, 'competitors' => 0, 'signals' => 0, 'rules' => MarketRules::get()];
        }

        $own_host = (string) wp_parse_url(get_home_url(), PHP_URL_HOST);
        $own_host = strtolower(preg_replace('~^www\.~', '', $own_host));

        $comp_urls = [];
        $comp_ids = [];

        foreach ($queries as $q) {
            $items = $this->serp->search($q, $serp_limit);
            foreach ($items as $index => $item) {
                $u = isset($item['url']) ? (string) $item['url'] : '';
                $host = (string) wp_parse_url($u, PHP_URL_HOST);
                $host = strtolower(preg_replace('~^www\.~', '', $host));
                if ($host === '' || $host === $own_host) {
                    continue;
                }

                $pos = $index + 1;
                $id = $this->repo->upsert_from_serp($host, $q, $pos);
                if ($id <= 0) {
                    continue;
                }
                $comp_ids[$host] = $id;

                if (!isset($comp_urls[$host])) {
                    $comp_urls[$host] = [];
                }
                if (count($comp_urls[$host]) < $urls_per_competitor && $u !== '') {
                    $comp_urls[$host][] = $u;
                }
            }
        }

        // Не скануємо ігноровані конкуренти
        $competitors = $this->repo->list_competitors();
        $ignored_ids = [];
        foreach ($competitors as $c) {
            if (($c['status'] ?? '') === 'ignored') {
                $ignored_ids[(int) ($c['id'] ?? 0)] = true;
            }
        }

        $scanner = new CompetitiveScanner($this->repo);
        $signals = 0;

        foreach ($comp_urls as $host => $urls) {
            $cid = $comp_ids[$host] ?? 0;
            if ($cid <= 0 || isset($ignored_ids[$cid]) || empty($urls)) {
                continue;
            }
            $before = $this->count_signals_for_competitor($cid);
            $scanner->scan_competitor($cid, $urls);
            $after = $this->count_signals_for_competitor($cid);
            $signals += max(0, $after - $before);
        }

        $rules = MarketRules::compute_from_repo($this->repo);

        return [
            'ok' => true,
            'queries' => count($queries),
            'competitors' => count($comp_ids),
            'signals' => $signals,
            'rules' => $rules,
        ];
    }

    /**
     * @return array<int,string>
     */
    private function get_market_queries(int $max): array {
        $max = max(1, min(20, $max));

        // 1) GSC snapshots (якщо є)
        $site_id = (int) crc32(get_home_url());
        $snap = new SnapshotRepository();
        $latest = $snap->get_latest('gsc', $site_id, 2);

        $qs = [];
        foreach ($latest as $row) {
            $data = $row['data']['data'] ?? null;
            if (!is_array($data) || empty($data['rows']) || !is_array($data['rows'])) {
                continue;
            }
            foreach ($data['rows'] as $r) {
                if (!is_array($r) || empty($r['keys']) || !is_array($r['keys'])) {
                    continue;
                }
                $q = (string) ($r['keys'][0] ?? '');
                $q = trim($q);
                if ($q !== '') {
                    $qs[] = $q;
                }
            }
        }

        $qs = array_values(array_unique($qs));

        // 2) Fallback: ключові слова постів, якщо GSC ще не налаштовано
        if (empty($qs)) {
            $posts = get_posts([
                'post_type' => 'any',
                'post_status' => 'publish',
                'numberposts' => 50,
                'fields' => 'ids',
            ]);
            foreach ($posts as $pid) {
                $kw = (string) get_post_meta((int) $pid, '_seojusai_keyword', true);
                $kw = trim($kw);
                if ($kw !== '') {
                    $qs[] = $kw;
                }
            }
            $qs = array_values(array_unique($qs));
        }

        return array_slice($qs, 0, $max);
    }

    private function count_signals_for_competitor(int $cid): int {
        global $wpdb;
        $table = $wpdb->prefix . 'seojusai_competitor_signals';
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
        $n = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$table} WHERE competitor_id=%d", $cid));
        return max(0, $n);
    }
}
