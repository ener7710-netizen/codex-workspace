<?php
declare(strict_types=1);

namespace SEOJusAI\Tasks;

use SEOJusAI\AI\ZeroShotClassifier;

defined('ABSPATH') || exit;

final class ClassifyPageTask {

    public function __invoke(array $payload): array {
        $post_id = (int) ($payload['post_id'] ?? 0);
        $taxonomy = (string) ($payload['taxonomy'] ?? '');

        $post = get_post($post_id);
        if (!$post) {
            return ['error' => 'Post not found'];
        }

        $text = trim($post->post_title . "\n" . wp_strip_all_tags($post->post_content));

        return ZeroShotClassifier::classify($text, $taxonomy);
    }
}