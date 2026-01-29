<?php
declare(strict_types=1);

namespace SEOJusAI\Vectors;

use SEOJusAI\SaaS\TenantContext;
use wpdb;

defined('ABSPATH') || exit;

final class VectorStore {

    private const CACHE_GROUP = 'seojusai_vectors';
    private const CACHE_TTL   = 600; // 10 хв

    private string $table;

    public function __construct(?wpdb $db = null) {
        global $wpdb;
        $db = $db instanceof wpdb ? $db : $wpdb;
        $this->table = $db->prefix . 'seojusai_vectors';
    }


    private function current_tenant_id(): string {
        return (string) TenantContext::tenant_id();
    }

    private function current_site_id(): int {
        return (int) crc32(get_home_url());
    }

    public function upsert(string $object_type, int $object_id, string $chunk_hash, string $content, array $embedding, string $model, int $dims): bool {
        global $wpdb;

        $object_type = sanitize_key($object_type);
        if ($object_type === '' || $object_id <= 0 || $chunk_hash === '') return false;

        $row_id = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$this->table} WHERE tenant_id=%s AND object_type=%s AND object_id=%d AND chunk_hash=%s",
            $this->current_tenant_id(),
            $object_type,
            $object_id,
            $chunk_hash
        ));

        $data = [
            'object_type' => $object_type,
            'object_id'   => $object_id,
            'chunk_hash'  => $chunk_hash,
            'content'     => $content,
            'embedding'   => wp_json_encode($embedding),
            'model'       => $model,
            'dims'        => $dims,
            'updated_at'  => current_time('mysql', true),
        ];

        if ($row_id > 0) {
            return $wpdb->update($this->table, $data, ['id' => $row_id]) !== false;
        }

        $data['created_at'] = current_time('mysql', true);
        return $wpdb->insert($this->table, $data) !== false;
    }

    /** @return array<int,array<string,mixed>> */
    public function search(array $query_embedding, int $limit = 8, ?string $object_type = null): array {
        global $wpdb;
        $limit = max(1, min(20, $limit));

        $hash = md5((string) wp_json_encode($query_embedding) . '|' . $limit . '|' . (string) $object_type);
        $cache_key = 'search_' . $hash;
        $cached = wp_cache_get($cache_key, self::CACHE_GROUP);
        if (is_array($cached)) {
            return $cached;
        }

        $args = [];
        $where = '';
        if ($object_type) {
            $where = 'WHERE object_type = %s';
            $args[] = sanitize_key($object_type);
        }

        $sql = "SELECT id, object_type, object_id, chunk_hash, content, embedding, model, dims FROM {$this->table} {$where} ORDER BY id DESC LIMIT 400";
        $rows = $args ? $wpdb->get_results($wpdb->prepare($sql, ...$args), ARRAY_A) : $wpdb->get_results($sql, ARRAY_A);
        if (!$rows) return [];

        $scored = [];
        foreach ($rows as $r) {
            $vec = json_decode((string)$r['embedding'], true);
            if (!is_array($vec)) continue;
            $r['score'] = $this->cosine($query_embedding, $vec);
            $scored[] = $r;
        }

        usort($scored, static fn($a,$b) => ($b['score'] <=> $a['score']));
         $result = array_slice($scored, 0, $limit);
        wp_cache_set($cache_key, $result, self::CACHE_GROUP, self::CACHE_TTL);
        return $result;
    }

    private function cosine(array $a, array $b): float {
        $n = min(count($a), count($b));
        if ($n === 0) return 0.0;
        $dot = 0.0; $na = 0.0; $nb = 0.0;
        for ($i=0; $i<$n; $i++) {
            $va = (float)$a[$i]; $vb = (float)$b[$i];
            $dot += $va*$vb;
            $na += $va*$va;
            $nb += $vb*$vb;
        }
        if ($na <= 0 || $nb <= 0) return 0.0;
        return $dot / (sqrt($na) * sqrt($nb));
    }
}
