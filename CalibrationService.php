<?php
declare(strict_types=1);

namespace SEOJusAI\Learning;

use SEOJusAI\AI\DecisionContract;

defined('ABSPATH') || exit;

/**
 * CalibrationService (v1)
 * - Збирає outcome після observed
 * - Рахує success-rate по action_slug
 * - Калібрує confidence/risk у наступних AI-рішеннях
 */
final class CalibrationService {

    private const OPT = 'seojusai_calibration_stats';

    /** @return array<string,mixed> */
    public static function stats(): array {
        $v = get_option(self::OPT, []);
        return is_array($v) ? $v : [];
    }

    public static function register(): void {
        add_action('seojusai/learning/observed', [self::class, 'on_observed'], 10, 1);
        add_filter('seojusai/ai/postprocess_decision', [self::class, 'postprocess_decision'], 20, 4);
    }

    /**
     * @param array<string,mixed> $ctx
     */
    public static function on_observed(array $ctx): void {
        $hash = isset($ctx['decision_hash']) ? sanitize_text_field((string)$ctx['decision_hash']) : '';
        if ($hash === '') return;

        $event = isset($ctx['event']) && is_array($ctx['event']) ? $ctx['event'] : [];
        $module = sanitize_key((string)($event['module_slug'] ?? ($ctx['module_slug'] ?? '')));
        $action = sanitize_key((string)($event['action_slug'] ?? ($ctx['action_slug'] ?? '')));

        $outcome = isset($ctx['outcome']) && is_array($ctx['outcome']) ? $ctx['outcome'] : [];
        $diff = isset($outcome['diff']) && is_array($outcome['diff']) ? $outcome['diff'] : [];

        $success = self::is_success($diff);

        $key = ($module ?: 'unknown') . '::' . ($action ?: 'unknown');

        $stats = self::stats();
        if (!isset($stats[$key]) || !is_array($stats[$key])) $stats[$key] = [
            'observed' => 0,
            'success' => 0,
            'avg_clicks_delta' => 0.0,
            'avg_impr_delta' => 0.0,
            'avg_pos_delta' => 0.0,
            'updated_at' => '',
        ];

        $row = $stats[$key];
        $obs = (int)($row['observed'] ?? 0) + 1;
        $succ = (int)($row['success'] ?? 0) + ($success ? 1 : 0);

        $clicks = isset($diff['clicks_delta']) ? (float)$diff['clicks_delta'] : 0.0;
        $impr   = isset($diff['impressions_delta']) ? (float)$diff['impressions_delta'] : 0.0;
        $pos    = isset($diff['position_delta']) ? (float)$diff['position_delta'] : 0.0;

        // online average
        $row['avg_clicks_delta'] = self::avg((float)($row['avg_clicks_delta'] ?? 0), $clicks, $obs);
        $row['avg_impr_delta']   = self::avg((float)($row['avg_impr_delta'] ?? 0), $impr, $obs);
        $row['avg_pos_delta']    = self::avg((float)($row['avg_pos_delta'] ?? 0), $pos, $obs);

        $row['observed'] = $obs;
        $row['success']  = $succ;
        $row['updated_at'] = current_time('mysql', true);

        $stats[$key] = $row;
        update_option(self::OPT, $stats, false);
    }

    /**
     * Калібрує AI-рішення перед валідацією/збереженням.
     * @param array<string,mixed> $decision
     * @return array<string,mixed>
     */
    public static function postprocess_decision(array $decision, array $context, string $scope, string $provider): array {
        if (!isset($decision['meta']) || !is_array($decision['meta'])) return $decision;
        if (!isset($decision['actions']) || !is_array($decision['actions'])) return $decision;

        // якщо контракт ще не валідний — не чіпаємо
        if (!DecisionContract::validate($decision)) {
            return $decision;
        }

        $meta_conf = (float)$decision['meta']['confidence'];
        $meta_risk = (string)$decision['meta']['risk'];

        $factors = [];
        foreach ($decision['actions'] as $a) {
            if (!is_array($a)) continue;
            $module = sanitize_key((string)($a['module'] ?? ($decision['meta']['module'] ?? 'unknown')));
            $action = sanitize_key((string)($a['action'] ?? 'unknown'));
            $factors[] = self::factor($module, $action);
        }

        $mult = 1.0;
        $risk_penalty = 0.0;
        if ($factors) {
            // average multiplier + risk penalty
            $mult = array_sum(array_column($factors, 'mult')) / count($factors);
            $risk_penalty = array_sum(array_column($factors, 'risk_penalty')) / count($factors);
        }

        $new_conf = max(0.0, min(1.0, $meta_conf * $mult));

        // risk adjustment: if penalty high -> bump risk
        $new_risk = $meta_risk;
        if ($risk_penalty >= 0.45) {
            $new_risk = 'high';
        } elseif ($risk_penalty >= 0.20 && $meta_risk === 'low') {
            $new_risk = 'medium';
        }

        $decision['meta']['confidence'] = $new_conf;
        $decision['meta']['risk'] = $new_risk;

        // also per-action confidence if present
        foreach ($decision['actions'] as $i => $a) {
            if (!is_array($a)) continue;
            $module = sanitize_key((string)($a['module'] ?? ($decision['meta']['module'] ?? 'unknown')));
            $action = sanitize_key((string)($a['action'] ?? 'unknown'));
            $fac = self::factor($module, $action);
            if (isset($decision['actions'][$i]['confidence']) && is_numeric($decision['actions'][$i]['confidence'])) {
                $decision['actions'][$i]['confidence'] = max(0.0, min(1.0, (float)$decision['actions'][$i]['confidence'] * (float)$fac['mult']));
            }
            if (isset($decision['actions'][$i]['risk']) && in_array((string)$decision['actions'][$i]['risk'], ['low','medium','high'], true)) {
                if ($fac['risk_penalty'] >= 0.45) $decision['actions'][$i]['risk'] = 'high';
                elseif ($fac['risk_penalty'] >= 0.20 && (string)$decision['actions'][$i]['risk'] === 'low') $decision['actions'][$i]['risk'] = 'medium';
            }
        }

        return $decision;
    }

    /** @return array{mult:float,risk_penalty:float,success_rate:float,observed:int} */
    public static function factor(string $module, string $action): array {
        $key = ($module ?: 'unknown') . '::' . ($action ?: 'unknown');
        $stats = self::stats();
        $row = isset($stats[$key]) && is_array($stats[$key]) ? $stats[$key] : null;

        $observed = $row ? (int)($row['observed'] ?? 0) : 0;
        $success  = $row ? (int)($row['success'] ?? 0) : 0;

        if ($observed < 5) {
            // not enough data: neutral
            return ['mult'=>1.0,'risk_penalty'=>0.0,'success_rate'=> ($observed ? $success/$observed : 0.0), 'observed'=>$observed];
        }

        $rate = $success / max(1, $observed);

        // multiplier: 0.85..1.20 based on success rate
        $mult = 0.85 + (0.35 * $rate); // rate=0 ->0.85, rate=1 ->1.20
        $mult = max(0.75, min(1.25, $mult));

        // risk penalty: if low rate -> increase
        $risk_penalty = max(0.0, min(0.7, 0.7 * (1.0 - $rate)));

        return ['mult'=>$mult,'risk_penalty'=>$risk_penalty,'success_rate'=>$rate,'observed'=>$observed];
    }

    /** @param array<string,mixed> $diff */
    private static function is_success(array $diff): bool {
        // If we have clicks or position improvements, count success.
        $clicks = isset($diff['clicks_delta']) ? (float)$diff['clicks_delta'] : 0.0;
        $impr   = isset($diff['impressions_delta']) ? (float)$diff['impressions_delta'] : 0.0;
        $pos    = isset($diff['position_delta']) ? (float)$diff['position_delta'] : 0.0; // negative means improved

        if ($clicks > 0) return true;
        if ($pos < -0.3 && $impr >= 0) return true;
        if ($impr > 20 && $pos <= 0) return true;
        return false;
    }

    private static function avg(float $prev_avg, float $value, int $n): float {
        if ($n <= 1) return $value;
        return $prev_avg + (($value - $prev_avg) / $n);
    }
}
