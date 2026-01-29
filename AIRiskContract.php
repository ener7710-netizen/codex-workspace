<?php
declare(strict_types=1);

namespace SEOJusAI\AIRiskFunnel\Contracts;

defined('ABSPATH') || exit;

/**
 * AIRiskContract (v1)
 *
 * Структура результату аналізу "ризик → рішення → звернення до адвоката".
 * Без реклами. Тільки юридична логіка та ясність.
 */
final class AIRiskContract {

    /** @return array<string,mixed> */
    public static function empty(): array {
        return [
            'ok' => true,
            'scope' => 'criminal',
            'post_id' => 0,
            'score' => [
                'client_intent' => 0,
                'risk_signal' => 0,
                'consequences' => 0,
                'decision_gap' => 0,
            ],
            'signals' => [
                'risk_terms' => [],
                'sanctions_terms' => [],
                'process_terms' => [],
                'consequence_terms' => [],
                'decision_gap_terms' => [],
            ],
            'recommendations' => [],
            'meta' => [
                'tone' => 'optimal',
                'version' => 1,
                'generated_at' => current_time('mysql', true),
            ],
        ];
    }

    public static function validate(array $data): bool {
        if (!isset($data['ok'])) return false;
        if (!isset($data['score']) || !is_array($data['score'])) return false;
        if (!isset($data['recommendations']) || !is_array($data['recommendations'])) return false;
        return true;
    }
}
