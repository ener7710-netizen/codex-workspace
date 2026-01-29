<?php
declare(strict_types=1);

namespace SEOJusAI\AI;

defined('ABSPATH') || exit;

/**
 * Simple domain-agnostic debias layer for zero-shot classification.
 * Uses neutral baseline probability correction.
 */
final class Debiaser {

    public static function correct(array $prediction, array $baseline): array {
        // Expect ['label'=>string,'confidence'=>float]
        $p = max(0.001, (float)($prediction['confidence'] ?? 0.0));
        $b = max(0.001, (float)($baseline[$prediction['label']] ?? 0.001));

        // log-odds correction
        $logit = log($p / (1 - $p)) - log($b / (1 - $b));
        $corrected = 1 / (1 + exp(-$logit));

        $prediction['confidence_raw'] = $p;
        $prediction['confidence_debiased'] = round($corrected, 4);
        $prediction['confidence'] = $prediction['confidence_debiased'];

        return $prediction;
    }
}
