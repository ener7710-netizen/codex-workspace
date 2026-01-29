<?php
declare(strict_types=1);

namespace SEOJusAI\Repository;

defined('ABSPATH')||exit;

final class SeoMetaRepository {

    public static function save(string $decision_hash,int $post_id,array $meta): void {
        global $wpdb;
        $table=$wpdb->prefix.'seojusai_seo_meta';
        $wpdb->replace($table,[
            'decision_hash'=>$decision_hash,
            'post_id'=>$post_id,
            'seo_title'=>$meta['seo_title']??null,
            'meta_description'=>$meta['meta_description']??null,
            'status'=>'planned',
            'created_at'=>current_time('mysql',true),
        ],['%s','%d','%s','%s','%s','%s']);
    }

    public static function get_by_decision(string $decision_hash): ?object {
        global $wpdb;
        $table=$wpdb->prefix.'seojusai_seo_meta';
        return $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE decision_hash=%s",$decision_hash));
    }

    public static function mark(string $decision_hash,string $status): void {
        global $wpdb;
        $table=$wpdb->prefix.'seojusai_seo_meta';
        $wpdb->update($table,['status'=>$status],['decision_hash'=>$decision_hash],['%s'],['%s']);
    }
}
