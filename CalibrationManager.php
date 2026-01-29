<?php
declare(strict_types=1);

namespace SEOJusAI\Calibration;

defined('ABSPATH') || exit;


final class CalibrationManager {
    public function freezeBaseline(int $postId): void {
        update_post_meta($postId, '_seojusai_baseline_frozen', time());
    }

    public function recordMetric(int $postId, string $key, float $value): void {
        $data = (array) get_post_meta($postId, '_seojusai_calibration', true);
        $data[$key] = $value;
        update_post_meta($postId, '_seojusai_calibration', $data);
    }

    public function isStable(int $postId): bool {
        $data = (array) get_post_meta($postId, '_seojusai_calibration', true);
        return isset($data['volatility']) && $data['volatility'] < 0.2;
    }
}
