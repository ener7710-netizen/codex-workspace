<?php
declare(strict_types=1);

namespace SEOJusAI\CaseLearning;

use SEOJusAI\AI\DecisionContract;

defined('ABSPATH') || exit;

/**
 * Case-based learning (v1)
 * - Збір внутрішніх кейсів (без персональних даних)
 * - Використання статистики кейсів для калібрування confidence/risk
 */
final class CaseLearningService {

    public static function register(): void {
        add_action('init', [CasePostType::class, 'register'], 9);

        // Автоматичний драфт-кейс після observed (лише як підготовка)
        add_action('seojusai/learning/observed', [self::class, 'on_observed'], 20, 1);

        // Вплив кейсів на AI-рішення
        add_filter('seojusai/ai/postprocess_decision', [self::class, 'postprocess_decision'], 15, 4);
    }

    /** @param array<string,mixed> $ctx */
    public static function on_observed(array $ctx): void {
        // Створюємо лише якщо outcome успішний та є action_key — як чернетку для редактора (без PII)
        $outcome = isset($ctx['outcome']) && is_array($ctx['outcome']) ? $ctx['outcome'] : [];
        $diff = isset($outcome['diff']) && is_array($outcome['diff']) ? $outcome['diff'] : [];
        $success = self::is_success($diff);

        if (!$success) return;

        $event = isset($ctx['event']) && is_array($ctx['event']) ? $ctx['event'] : [];
        $module = sanitize_key((string)($event['module_slug'] ?? ''));
        $action = sanitize_key((string)($event['action_slug'] ?? ''));

        if ($module === '' && $action === '') return;

        $action_key = ($module ?: 'unknown') . '::' . ($action ?: 'unknown');

        $repo = new CaseRepository();
        $title = sprintf(__('Кейс (авто): %s', 'seojusai'), $action_key);

        // короткий, без персональних деталей
        $content = __("Автоматично створений навчальний кейс на основі успішного результату після застосування рекомендації.

Заповніть коротко: контекст, що було зроблено, і чому спрацювало. Не додавайте персональні дані.", 'seojusai');

        $repo->create_auto_case([
            'title' => $title,
            'content' => $content,
            'practice' => 'criminal',
            'outcome' => 'positive',
            'action_key' => $action_key,
        ]);
    }

    /**
     * Вплив статистики кейсів на рішення.
     * @param array<string,mixed> $decision
     * @return array<string,mixed>
     */
    public static function postprocess_decision(array $decision, array $context, string $scope, string $provider): array {
        if (!DecisionContract::validate($decision)) return $decision;

        $practice = 'criminal';
        if (isset($context['practice']) && is_string($context['practice'])) {
            $p = sanitize_key($context['practice']);
            if (in_array($p, ['criminal','tax','civil'], true)) $practice = $p;
        }

        $repo = new CaseRepository();

        // aggregate multiplier from actions
        $mult = 1.0;
        $penalty = 0.0;
        $count = 0;

        $actions = isset($decision['actions']) && is_array($decision['actions']) ? $decision['actions'] : [];
        foreach ($actions as $a) {
            if (!is_array($a)) continue;
            $module = sanitize_key((string)($a['module'] ?? ($decision['meta']['module'] ?? 'unknown')));
            $action = sanitize_key((string)($a['action'] ?? 'unknown'));
            $key = ($module ?: 'unknown') . '::' . ($action ?: 'unknown');

            $st = $repo->stats_for_action($key, $practice);
            if (($st['observed'] ?? 0) < 3) continue; // не вистачає даних

            $rate = (float)($st['rate'] ?? 0.0);

            // кейси впливають сильніше, ніж загальна calibration, але обережно
            $m = 0.90 + (0.30 * $rate); // 0 ->0.90, 1 ->1.20
            $m = max(0.80, min(1.25, $m));

            $mult += $m;
            $penalty += (1.0 - $rate);
            $count++;
        }

        if ($count > 0) {
            $mult = $mult / $count;
            $penalty = $penalty / $count;

            $conf = (float)$decision['meta']['confidence'];
            $conf = max(0.0, min(1.0, $conf * $mult));
            $decision['meta']['confidence'] = $conf;

            // risk bump if penalty high
            $risk = (string)$decision['meta']['risk'];
            if ($penalty >= 0.60) $risk = 'high';
            elseif ($penalty >= 0.30 && $risk === 'low') $risk = 'medium';
            $decision['meta']['risk'] = $risk;
        }

        return $decision;
    }

    /** @param array<string,mixed> $diff */
    private static function is_success(array $diff): bool {
        $clicks = isset($diff['clicks_delta']) ? (float)$diff['clicks_delta'] : 0.0;
        $impr   = isset($diff['impressions_delta']) ? (float)$diff['impressions_delta'] : 0.0;
        $pos    = isset($diff['position_delta']) ? (float)$diff['position_delta'] : 0.0;

        if ($clicks > 0) return true;
        if ($pos < -0.3 && $impr >= 0) return true;
        if ($impr > 20 && $pos <= 0) return true;
        return false;
    }
}
