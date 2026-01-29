<?php
declare(strict_types=1);

namespace SEOJusAI\Tasks;

use SEOJusAI\Analyze\PageAuditSummary;
use SEOJusAI\Crawl\HtmlSnapshot;

defined('ABSPATH') || exit;

/**
 * AuditPostJob
 * Refreshes front HTML snapshot + stores audit summary meta for list table/editor UX.
 */
final class AuditPostJob {

    public static function run(int $post_id): void {
        if ($post_id <= 0) {
            return;
        }

        // 1) Ensure front snapshot exists (captures theme/header/footer/popup markup)
        if (class_exists(HtmlSnapshot::class)) {
            HtmlSnapshot::refresh_for_post($post_id, true);
        }

        // 2) Compute + store summary
        $summary = PageAuditSummary::compute($post_id, false);
        if (is_array($summary) && !empty($summary['ok'])) {
            PageAuditSummary::store($post_id, $summary);
        }
    }
}
