<?php
declare(strict_types=1);

namespace SEOJusAI\GA4;

use SEOJusAI\Snapshots\SnapshotRepository;

defined('ABSPATH') || exit;

/**
 * Ga4Snapshot
 *
 * Зберігає GA4 дані у загальній таблиці seojusai_snapshots (type='ga4').
 * Це мінімізує схему БД і не ламає існуючий SnapshotRepository.
 */
final class Ga4Snapshot {

    /**
     * @param array<string,mixed> $payload
     */
    public static function save(array $payload): void {
        $site = (string) home_url('/');
        $site_id = (int) crc32($site);

        $repo = new SnapshotRepository();
        $repo->insert(
            'ga4',
            $site_id,
            [
                'site' => $site,
                'data' => $payload,
            ]
        );
    }

    /**
     * Повертає останній GA4-снапшот (за замовчуванням — останній).
     *
     * @return array<string,mixed>|null
     */
    public static function latest(): ?array {
        $repo = new SnapshotRepository();
        if (!$repo->exists()) {
            return null;
        }
        global $wpdb;
        $table = $wpdb->prefix . 'seojusai_snapshots';
        $row = $wpdb->get_row(
            "SELECT id, data_json, created_at FROM {$table} WHERE type = 'ga4' ORDER BY id DESC LIMIT 1",
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
}
