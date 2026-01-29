<?php
declare(strict_types=1);

namespace SEOJusAI\SEO;

use SEOJusAI\Repository\SeoMetaRepository;

defined('ABSPATH')||exit;

final class SeoMetaApplier {

    public static function apply(string $decision_hash): bool {
        $meta = SeoMetaRepository::get_by_decision($decision_hash);
        if (!$meta || $meta->status !== 'approved') {
            return false;
        }

        $post_id = (int)$meta->post_id;
        if ($post_id <= 0) return false;

        // Apply to WordPress core
        if (!empty($meta->seo_title)) {
            wp_update_post([
                'ID' => $post_id,
                'post_title' => wp_strip_all_tags($meta->seo_title),
            ]);
        }

        if (!empty($meta->meta_description)) {
            update_post_meta($post_id, '_seojusai_meta_description', wp_strip_all_tags($meta->meta_description));
        }

        // Yoast SEO
        if (defined('WPSEO_VERSION')) {
            if (!empty($meta->seo_title)) {
                update_post_meta($post_id, '_yoast_wpseo_title', wp_strip_all_tags($meta->seo_title));
            }
            if (!empty($meta->meta_description)) {
                update_post_meta($post_id, '_yoast_wpseo_metadesc', wp_strip_all_tags($meta->meta_description));
            }
        }

        // Rank Math
        if (defined('RANK_MATH_VERSION')) {
            if (!empty($meta->seo_title)) {
                update_post_meta($post_id, 'rank_math_title', wp_strip_all_tags($meta->seo_title));
            }
            if (!empty($meta->meta_description)) {
                update_post_meta($post_id, 'rank_math_description', wp_strip_all_tags($meta->meta_description));
            }
        }

        SeoMetaRepository::mark($decision_hash, 'applied');
        return true;
    }
}
