<?php
declare(strict_types=1);

namespace SEOJusAI\Competitive;

defined('ABSPATH') || exit;

/**
 * CompetitiveRepository
 *
 * Зберігає конкурентів та агреговані сигнали.
 * ВАЖЛИВО: ми НЕ копіюємо тексти конкурентів, лише сигнали (наявність soft CTA, позиція, тип сторінки).
 */
final class CompetitiveRepository {

    private string $t_comp;
    private string $t_sig;

    public function __construct() {
        global $wpdb;
        $this->t_comp = $wpdb->prefix . 'seojusai_competitors';
        $this->t_sig  = $wpdb->prefix . 'seojusai_competitor_signals';
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    public function list_competitors(): array {
        global $wpdb;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
        return (array) $wpdb->get_results("SELECT * FROM {$this->t_comp} ORDER BY id DESC", ARRAY_A);
    }

    public function add_competitor(string $url): int {
        // Deprecated: competitors must come from SERP/Gemini only.
        return 0;
    }

    public function delete_competitor(int $id): bool {
        // Deprecated: competitors must come from SERP/Gemini only.
        return false;
    }

    /**
     * Upsert competitor record from SERP/Gemini.
     *
     * @return int competitor_id
     */
    public function upsert_from_serp(string $domain, string $query_text, int $position): int {
        global $wpdb;

        $domain = strtolower(trim($domain));
        $domain = preg_replace('~^www\.~', '', $domain);
        $domain = sanitize_text_field($domain);
        if ($domain === '') {
            return 0;
        }

        $url = 'https://' . $domain;
        $query_text = sanitize_text_field($query_text);
        $position = max(1, min(50, (int) $position));

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
        $existing = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$this->t_comp} WHERE url=%s LIMIT 1",
            $url
        ));

        if ($existing > 0) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
            $row = (array) $wpdb->get_row($wpdb->prepare(
                "SELECT best_position, appearances FROM {$this->t_comp} WHERE id=%d LIMIT 1",
                $existing
            ), ARRAY_A);

            $best = isset($row['best_position']) ? (int) $row['best_position'] : 0;
            $app  = isset($row['appearances']) ? (int) $row['appearances'] : 0;

            $new_best = ($best <= 0) ? $position : min($best, $position);

            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
            $wpdb->update($this->t_comp, [
                'source'        => 'serp',
                'query_text'    => $query_text,
                'best_position' => $new_best,
                'appearances'   => $app + 1,
            ], ['id' => $existing], ['%s','%s','%d','%d'], ['%d']);

            return $existing;
        }

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
        $wpdb->insert($this->t_comp, [
            'url'          => $url,
            'source'       => 'serp',
            'query_text'   => $query_text,
            'best_position'=> $position,
            'appearances'  => 1,
            'status'       => 'new',
            'last_scan_at' => null,
            'created_at'   => current_time('mysql'),
        ], ['%s','%s','%s','%d','%d','%s','%s','%s']);

        return (int) $wpdb->insert_id;
    }

    public function set_ignored(int $id, bool $ignored): void {
        global $wpdb;
        if ($id <= 0) return;
        $status = $ignored ? 'ignored' : 'new';
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
        $wpdb->update($this->t_comp, [
            'status' => $status,
        ], ['id' => $id], ['%s'], ['%d']);
    }

    public function mark_scanned(int $id, string $status = 'ok'): void {
        global $wpdb;
        if ($id <= 0) return;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
        $wpdb->update($this->t_comp, [
            'status' => $status,
            'last_scan_at' => current_time('mysql'),
        ], ['id' => $id], ['%s','%s'], ['%d']);
    }

    public function upsert_signal(int $competitor_id, string $url, string $page_type, bool $has_soft_cta, string $cta_position): void {
        global $wpdb;
        $competitor_id = max(0, $competitor_id);
        $url = esc_url_raw($url);
        $page_type = sanitize_key($page_type);
        $cta_position = sanitize_key($cta_position);
        if ($competitor_id <= 0 || !$url) return;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
        $existing = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$this->t_sig} WHERE competitor_id=%d AND url=%s LIMIT 1",
            $competitor_id,
            $url
        ));

        $data = [
            'competitor_id' => $competitor_id,
            'url' => $url,
            'page_type' => $page_type ?: 'unknown',
            'has_soft_cta' => $has_soft_cta ? 1 : 0,
            'cta_position' => $cta_position ?: 'none',
            'updated_at' => current_time('mysql'),
        ];

        if ($existing > 0) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
            $wpdb->update($this->t_sig, $data, ['id' => $existing], ['%d','%s','%s','%d','%s','%s'], ['%d']);
        } else {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
            $wpdb->insert($this->t_sig, $data, ['%d','%s','%s','%d','%s','%s']);
        }
    }

    /**
     * @return array{total:int, by_type:array<string,array{total:int, with_cta:int, pct:float}>}
     */
    public function summary(): array {
        global $wpdb;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
        $rows = (array) $wpdb->get_results("SELECT page_type, COUNT(*) AS total, SUM(has_soft_cta) AS with_cta FROM {$this->t_sig} GROUP BY page_type", ARRAY_A);

        $by = [];
        $total = 0;
        foreach ($rows as $r) {
            $t = (string) ($r['page_type'] ?? 'unknown');
            $tt = (int) ($r['total'] ?? 0);
            $wc = (int) ($r['with_cta'] ?? 0);
            $pct = $tt > 0 ? ($wc / $tt) : 0.0;
            $by[$t] = ['total' => $tt, 'with_cta' => $wc, 'pct' => $pct];
            $total += $tt;
        }

        return ['total' => $total, 'by_type' => $by];
    }
}
