<?php
declare(strict_types=1);

namespace SEOJusAI\Learning;

defined('ABSPATH') || exit;

final class LearningLoop {

    private LearningRepository $repo;
    private WeightManager $weights;

    public function __construct(?LearningRepository $repo = null, ?WeightManager $weights = null) {
        $this->repo = $repo ?? new LearningRepository();
        $this->weights = $weights ?? new WeightManager();
    }

    public function run_weekly(): void {
        $rows = $this->repo->recent(120, 500);
        if (empty($rows) || count($rows) < 10) return;

        $w = $this->weights->get();

        $n = 0;
        $acc = 0.0;

        foreach ($rows as $r) {
            $pred = (float)($r['predicted_impact'] ?? 0);
            $obs  = (float)($r['observed_clicks_delta'] ?? 0);
            if ($pred <= 0) continue;

            $ratio = ($obs + 1.0) / ($pred + 1.0);
            $acc += $ratio;
            $n++;
        }

        if ($n < 10) return;
        $avg = $acc / $n;

        $delta = max(-0.05, min(0.05, ($avg - 1.0) * 0.10));

        $w['demand']    = $this->clamp($w['demand'] + $delta, 0.5, 1.8);
        $w['proximity'] = $this->clamp($w['proximity'] + $delta, 0.5, 1.8);
        $w['effort']    = $this->clamp($w['effort'] - $delta, 0.5, 1.8);

        $this->weights->set($w);
    }

    private function clamp(float $v, float $min, float $max): float {
        return max($min, min($max, $v));
    }
}
