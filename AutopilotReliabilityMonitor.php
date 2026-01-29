<?php
declare(strict_types=1);

namespace SEOJusAI\Autopilot;

defined('ABSPATH') || exit;

final class AutopilotReliabilityMonitor {

    public const HOOK = 'seojusai/background/autopilot_health_tick';

    public function register(): void {
        add_action(self::HOOK, [$this, 'tick']);
    }

    public function tick(): void {
        // Do not pause during emergency stop; emergency stop is stronger anyway.
        if (class_exists('SEOJusAI\\Core\\EmergencyStop') && \SEOJusAI\Core\EmergencyStop::is_active()) {
            return;
        }

        $metrics = $this->compute_metrics(14);

        AutopilotReliability::set_health($metrics);

        $thr = AutopilotReliability::thresholds();

        // Pause triggers: high failure rate with enough sample, or any high-risk outcomes from autopilot.
        $sample = (int)($metrics['sample'] ?? 0);
        $fail_rate = (float)($metrics['fail_rate'] ?? 0.0);
        $high_risk = (int)($metrics['high_risk'] ?? 0);

        if ($high_risk > 0) {
            if (!AutopilotReliability::is_paused()) {
                AutopilotReliability::pause('high_risk_detected', ['high_risk' => $high_risk]);
            }
            return;
        }

        if ($sample >= (int)$thr['min_sample'] && $fail_rate >= (float)$thr['max_fail_rate']) {
            if (!AutopilotReliability::is_paused()) {
                AutopilotReliability::pause('high_fail_rate', [
                    'sample' => $sample,
                    'fail_rate' => $fail_rate,
                    'max_fail_rate' => (float)$thr['max_fail_rate'],
                ]);
            }
            return;
        }

        // Auto-resume: if paused by monitor and conditions now healthy
        $st = AutopilotReliability::status();
        if ($st['paused'] && in_array($st['reason'], ['high_fail_rate','high_risk_detected'], true)) {
            if ($sample >= (int)$thr['min_sample'] && $fail_rate < (float)$thr['max_fail_rate'] * 0.8 && $high_risk === 0) {
                AutopilotReliability::resume('monitor');
            }
        }
    }

    /** @return array<string,mixed> */
    private function compute_metrics(int $days): array {
        global $wpdb;
        $days = max(7, min(90, $days));
        $since = time() - ($days * DAY_IN_SECONDS);

        $t_dec = $wpdb->prefix . 'seojusai_decisions';
        $t_out = $wpdb->prefix . 'seojusai_outcomes';

        // Join decisions/outcomes for autopilot source
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT d.risk_level, o.status
                 FROM {$t_dec} d
                 LEFT JOIN {$t_out} o ON o.decision_id = d.id
                 WHERE d.created_at >= %d AND d.source = %s
                 ORDER BY d.created_at DESC
                 LIMIT 200",
                $since, 'autopilot'
            ),
            ARRAY_A
        );
        $rows = is_array($rows) ? $rows : [];

        $applied = 0; $failed = 0; $rejected = 0; $unknown = 0; $high_risk = 0;

        foreach ($rows as $r) {
            $risk = sanitize_key((string)($r['risk_level'] ?? 'unknown'));
            if (in_array($risk, ['high','critical'], true)) $high_risk++;

            $st = sanitize_key((string)($r['status'] ?? 'unknown'));
            if ($st === 'applied') $applied++;
            elseif ($st === 'failed') $failed++;
            elseif ($st === 'rejected') $rejected++;
            else $unknown++;
        }

        $sample = $applied + $failed;
        $fail_rate = $sample > 0 ? round($failed / $sample, 4) : 0.0;

        return [
            'window_days' => $days,
            'ts' => time(),
            'applied' => $applied,
            'failed' => $failed,
            'rejected' => $rejected,
            'unknown' => $unknown,
            'sample' => $sample,
            'fail_rate' => $fail_rate,
            'high_risk' => $high_risk,
        ];
    }
}
