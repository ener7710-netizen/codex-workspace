<?php
declare(strict_types=1);

namespace SEOJusAI\Learning;

defined('ABSPATH') || exit;

final class WeightManager {

    private const OPT = 'seojusai_opportunity_weights';

    public function get(): array {
        $w = get_option(self::OPT, []);
        if (!is_array($w) || empty($w)) {
            $w = [
                'demand' => 1.0,
                'proximity' => 1.0,
                'authority' => 1.0,
                'intent' => 1.0,
                'effort' => 1.0,
                'risk' => 1.0,
            ];
        }
        return $w;
    }

    public function set(array $w): void {
        // Normalize and clamp to keep learning predictable
        $keys = ['demand','proximity','authority','intent','effort','risk'];
        $out = [];
        foreach ($keys as $k) {
            $v = isset($w[$k]) ? (float) $w[$k] : 1.0;
            // hard bounds to prevent runaway configs
            $v = max(0.1, min(5.0, $v));
            $out[$k] = $v;
        }
        update_option(self::OPT, $out, false);
    }
}
