<?php
declare(strict_types=1);

namespace SEOJusAI\AIRiskFunnel\Scoring;

defined('ABSPATH') || exit;

final class ClientIntentScore {

    /** @return array{client_intent:int} */
    public function score(float $risk_signal, float $consequences, float $decision_gap): array {

        $risk_signal  = max(0.0, min(1.0, $risk_signal));
        $consequences = max(0.0, min(1.0, $consequences));
        $decision_gap = max(0.0, min(1.0, $decision_gap));

        // Optimal weights for criminal funnel
        $v = ($risk_signal * 0.40) + ($consequences * 0.30) + ($decision_gap * 0.30);

        return ['client_intent' => (int) round($v * 100)];
    }
}
