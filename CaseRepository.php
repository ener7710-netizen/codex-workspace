<?php
declare(strict_types=1);

namespace SEOJusAI\CaseLearning;

defined('ABSPATH') || exit;

final class CaseRepository {

    /** @return array<int,array<string,mixed>> */
    public function list_recent(int $limit = 50, string $practice = ''): array {
        $limit = max(1, min(200, $limit));
        $args = [
            'post_type' => CasePostType::CPT,
            'post_status' => ['publish','draft','private'],
            'posts_per_page' => $limit,
            'orderby' => 'date',
            'order' => 'DESC',
        ];

        if ($practice && in_array($practice, ['criminal','tax','civil'], true)) {
            $args['meta_query'] = [
                [
                    'key' => '_seojusai_case_practice',
                    'value' => $practice,
                    'compare' => '='
                ]
            ];
        }

        $q = new \WP_Query($args);
        $items = [];
        foreach ($q->posts as $p) {
            $items[] = [
                'id' => (int)$p->ID,
                'title' => (string)$p->post_title,
                'date' => (string)$p->post_date_gmt,
                'practice' => (string)get_post_meta($p->ID, '_seojusai_case_practice', true),
                'outcome' => (string)get_post_meta($p->ID, '_seojusai_case_outcome', true),
                'action_key' => (string)get_post_meta($p->ID, '_seojusai_case_action_key', true),
            ];
        }
        wp_reset_postdata();
        return $items;
    }

    /**
     * Статистика по ключу дії (module::action) на основі кейсів.
     * @return array{observed:int,positive:int,negative:int,rate:float}
     */
    public function stats_for_action(string $action_key, string $practice = ''): array {
        $action_key = sanitize_text_field($action_key);
        if ($action_key === '') return ['observed'=>0,'positive'=>0,'negative'=>0,'rate'=>0.0];

        $meta = [
            [
                'key' => '_seojusai_case_action_key',
                'value' => $action_key,
                'compare' => '='
            ]
        ];
        if ($practice && in_array($practice, ['criminal','tax','civil'], true)) {
            $meta[] = [
                'key' => '_seojusai_case_practice',
                'value' => $practice,
                'compare' => '='
            ];
        }

        $q = new \WP_Query([
            'post_type' => CasePostType::CPT,
            'post_status' => ['publish','draft','private'],
            'posts_per_page' => 200,
            'meta_query' => $meta,
            'fields' => 'ids',
        ]);

        $obs=0; $pos=0; $neg=0;
        foreach ($q->posts as $id) {
            $obs++;
            $out = (string)get_post_meta((int)$id, '_seojusai_case_outcome', true);
            if ($out === 'positive') $pos++;
            if ($out === 'negative') $neg++;
        }

        $rate = $obs ? ($pos / $obs) : 0.0;
        return ['observed'=>$obs,'positive'=>$pos,'negative'=>$neg,'rate'=>$rate];
    }

    public function create_auto_case(array $ctx): int {
        $title = (string)($ctx['title'] ?? '');
        $content = (string)($ctx['content'] ?? '');
        $practice = sanitize_key((string)($ctx['practice'] ?? 'criminal'));
        $outcome = sanitize_key((string)($ctx['outcome'] ?? 'neutral'));
        $action_key = sanitize_text_field((string)($ctx['action_key'] ?? ''));

        if ($title === '') $title = __('Кейс (авто)', 'seojusai');
        if (!in_array($practice, ['criminal','tax','civil'], true)) $practice = 'criminal';
        if (!in_array($outcome, ['positive','neutral','negative'], true)) $outcome = 'neutral';

        $id = wp_insert_post([
            'post_type' => CasePostType::CPT,
            'post_status' => 'draft',
            'post_title' => $title,
            'post_content' => $content,
        ], true);

        if (is_wp_error($id)) return 0;

        update_post_meta((int)$id, '_seojusai_case_practice', $practice);
        update_post_meta((int)$id, '_seojusai_case_outcome', $outcome);
        update_post_meta((int)$id, '_seojusai_case_action_key', $action_key);

        // no personal data
        return (int)$id;
    }
}
