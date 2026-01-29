<?php
declare(strict_types=1);

namespace SEOJusAI\Autopilot;

defined('ABSPATH') || exit;

/**
 * AutopilotReliability
 *
 * Глобальний "гальмо-педаль" для автопілота:
 * - pause/resume
 * - thresholds (confidence/failure)
 * - health snapshot
 */
final class AutopilotReliability {

    private const OPT_PAUSED = 'seojusai_autopilot_paused';
    private const OPT_REASON = 'seojusai_autopilot_pause_reason';
    private const OPT_HEALTH = 'seojusai_autopilot_health_v1';
    private const OPT_THRESH = 'seojusai_autopilot_reliability_thresholds_v1';

    /** @return array{paused:bool, reason:string, since:int} */
    public static function status(): array {
        $paused = (int) get_option(self::OPT_PAUSED, 0) === 1;
        $reason = (string) get_option(self::OPT_REASON, '');
        $health = self::health();
        $since = isset($health['paused_since']) ? (int)$health['paused_since'] : 0;

        return [
            'paused' => $paused,
            'reason' => $reason,
            'since'  => $since,
        ];
    }

    public static function is_paused(): bool {
        return (int) get_option(self::OPT_PAUSED, 0) === 1;
    }

    /** @param array<string,mixed> $meta */
    public static function pause(string $reason, array $meta = []): void {
        update_option(self::OPT_PAUSED, 1, false);
        update_option(self::OPT_REASON, sanitize_text_field($reason), false);

        $health = self::health();
        $health['paused_since'] = time();
        $health['pause_reason'] = sanitize_text_field($reason);
        $health['pause_meta'] = $meta;
        self::set_health($health);

        do_action('seojusai/autopilot/paused', [
            'reason' => $reason,
            'meta' => $meta,
            'ts' => time(),
        ]);
    }

    public static function resume(string $by = 'manual'): void {
        update_option(self::OPT_PAUSED, 0, false);
        update_option(self::OPT_REASON, '', false);

        $health = self::health();
        $health['paused_since'] = 0;
        $health['pause_reason'] = '';
        $health['resume_by'] = sanitize_key($by);
        $health['resume_at'] = time();
        self::set_health($health);

        do_action('seojusai/autopilot/resumed', [
            'by' => $by,
            'ts' => time(),
        ]);
    }

    /** @return array<string,mixed> */
    public static function thresholds(): array {
        $t = get_option(self::OPT_THRESH, []);
        if (!is_array($t)) $t = [];

        $min_conf = isset($t['min_confidence']) ? (float)$t['min_confidence'] : 0.70;
        if ($min_conf < 0) $min_conf = 0;
        if ($min_conf > 1) $min_conf = 1;

        $max_fail = isset($t['max_fail_rate']) ? (float)$t['max_fail_rate'] : 0.25;
        if ($max_fail < 0) $max_fail = 0;
        if ($max_fail > 1) $max_fail = 1;

        $min_sample = isset($t['min_sample']) ? (int)$t['min_sample'] : 10;
        $min_sample = max(5, min(200, $min_sample));

        return [
            'min_confidence' => $min_conf,
            'max_fail_rate'  => $max_fail,
            'min_sample'     => $min_sample,
        ];
    }

    public static function set_thresholds(float $min_confidence, float $max_fail_rate, int $min_sample): void {
        $min_confidence = max(0.0, min(1.0, $min_confidence));
        $max_fail_rate  = max(0.0, min(1.0, $max_fail_rate));
        $min_sample     = max(5, min(200, $min_sample));

        update_option(self::OPT_THRESH, [
            'min_confidence' => $min_confidence,
            'max_fail_rate'  => $max_fail_rate,
            'min_sample'     => $min_sample,
        ], false);
    }

    /** @return array<string,mixed> */
    public static function health(): array {
        $h = get_option(self::OPT_HEALTH, []);
        return is_array($h) ? $h : [];
    }

    /** @param array<string,mixed> $health */
    public static function set_health(array $health): void {
        update_option(self::OPT_HEALTH, $health, false);
    }

    /** Extract normalized confidence from a decision payload. */
    public static function extract_confidence(array $decision): float {
        $c = 0.0;
        if (isset($decision['confidence'])) $c = (float)$decision['confidence'];
        elseif (isset($decision['meta']) && is_array($decision['meta']) && isset($decision['meta']['confidence'])) $c = (float)$decision['meta']['confidence'];

        if ($c > 1.0) $c = $c / 100.0; // support 0..100
        if ($c < 0.0) $c = 0.0;
        if ($c > 1.0) $c = 1.0;
        return $c;
    }

    /** Extract risk level (low/medium/high/critical/unknown). */
    public static function extract_risk(array $decision): string {
        $r = '';
        if (isset($decision['risk_level'])) $r = (string)$decision['risk_level'];
        elseif (isset($decision['risk'])) $r = (string)$decision['risk'];
        elseif (isset($decision['meta']) && is_array($decision['meta']) && isset($decision['meta']['risk_level'])) $r = (string)$decision['meta']['risk_level'];

        $r = sanitize_key($r);
        return $r !== '' ? $r : 'unknown';
    }
}
