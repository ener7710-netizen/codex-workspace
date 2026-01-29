<?php
declare(strict_types=1);

namespace SEOJusAI\SaaS;

defined('ABSPATH') || exit;

final class PlanCatalog {

    /**
     * Limits are soft-enforced in v2.0 foundation:
     * block heavy operations when exceeding limits (vectors, audits/day, bulk apply/day).
     */
    public static function get(string $plan): array {
        $plan = sanitize_key($plan);

        $plans = [
            'local' => [
                'label' => 'Local',
                'max_vectors' => 250000,
                'max_audits_per_day' => 250,
                'max_bulk_apply_per_day' => 50,
                'allow_remote_compute' => false,
            ],
            'pro' => [
                'label' => 'Pro',
                'max_vectors' => 1000000,
                'max_audits_per_day' => 2000,
                'max_bulk_apply_per_day' => 500,
                'allow_remote_compute' => true,
            ],
            'enterprise' => [
                'label' => 'Enterprise',
                'max_vectors' => 5000000,
                'max_audits_per_day' => 20000,
                'max_bulk_apply_per_day' => 5000,
                'allow_remote_compute' => true,
            ],
        ];

        return $plans[$plan] ?? $plans['local'];
    }
}
