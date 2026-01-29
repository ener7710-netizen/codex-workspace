<?php
declare(strict_types=1);

namespace SEOJusAI\Learning;

defined('ABSPATH') || exit;

final class PostOutcomeCollector implements OutcomeCollectorInterface {

    public function before(string $entity_type, int $entity_id): array {
        return $this->snapshot($entity_type, $entity_id);
    }

    public function after(string $entity_type, int $entity_id): array {
        return $this->snapshot($entity_type, $entity_id);
    }

    public function diff(array $before, array $after): array {
        $out = [
            'post_modified_before' => $before['post_modified_gmt'] ?? null,
            'post_modified_after'  => $after['post_modified_gmt'] ?? null,
        ];
        // generic numeric deltas
        foreach (['seo_score','word_count'] as $k) {
            $b = isset($before[$k]) ? (float)$before[$k] : null;
            $a = isset($after[$k]) ? (float)$after[$k] : null;
            if ($b !== null && $a !== null) {
                $out[$k . '_delta'] = round($a - $b, 4);
            }
        }
        return $out;
    }

    /** @return array<string,mixed> */
    private function snapshot(string $entity_type, int $entity_id): array {
        if ($entity_type !== 'post' || $entity_id <= 0) return [];

        $post = get_post($entity_id);
        if (!$post) return [];

        $content = wp_strip_all_tags((string)$post->post_content);
        $content = preg_replace('/\s+/', ' ', $content) ?? $content;

        $score = get_post_meta($entity_id, '_seojusai_score', true);
        $score = is_numeric($score) ? (float)$score : null;

        return [
            'post_id' => (int)$entity_id,
            'post_type' => (string)$post->post_type,
            'post_modified_gmt' => (string)$post->post_modified_gmt,
            'word_count' => str_word_count($content),
            'seo_score' => $score,
        ];
    }
}
