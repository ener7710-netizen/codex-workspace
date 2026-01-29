<?php
declare(strict_types=1);

namespace SEOJusAI\Modules;

use SEOJusAI\Core\Contracts\ModuleInterface;
use SEOJusAI\Core\Kernel;
use SEOJusAI\Tasks\TaskQueue;
use SEOJusAI\AIRiskFunnel\AIRiskFunnelService;

defined('ABSPATH') || exit;

/**
 * AIRiskFunnelModule (criminal focus, v1)
 * Мета: зробити контент "цитованим" AI та корисним для прийняття рішення (без реклами).
 */
final class AIRiskFunnelModule implements ModuleInterface {

    public function get_slug(): string { return 'risk_funnel'; }

    public function register(Kernel $kernel): void {
        $kernel->register_module($this->get_slug(), $this);
    }

    public function init(Kernel $kernel): void {

        add_action('save_post', function (int $post_id, \WP_Post $post) {
            if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
            if (wp_is_post_revision($post_id)) return;
            if ($post->post_status !== 'publish') return;

            // Focus: criminal pages. If unsure, analyze anyway when post contains criminal markers.
            $text = mb_strtolower((string)$post->post_title . ' ' . wp_strip_all_tags((string)$post->post_content));
            $is_criminal = (mb_strpos($text, 'криміналь') !== false) || (mb_strpos($text, 'підозр') !== false) || (mb_strpos($text, 'обшук') !== false);

            if (!$is_criminal) return;

            $bucket = (int) floor(time() / 120); // 2 хв "debounce"
            $key = 'riskfunnel:' . $post_id . ':' . $bucket;

            (new TaskQueue())->enqueue(AIRiskFunnelService::TASK_ANALYZE_POST, [
                'post_id' => $post_id,
                'priority' => 'low',
                'source' => 'system',
            ], $key);

        }, 35, 2);
    }
}
