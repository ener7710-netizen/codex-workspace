<?php
declare(strict_types=1);

namespace SEOJusAI\Competitive;

defined('ABSPATH') || exit;

/**
 * MarketRules
 *
 * Перетворює зібрані сигнали конкурентів у правила, які використовує Lead Funnel.
 *
 * Це НЕ копіювання конкурентів. Це ринкова калібровка: "чи прийнято" показувати soft CTA на подібних сторінках.
 */
final class MarketRules {

    private const OPTION_KEY = 'seojusai_market_rules_lead';

    public static function compute_from_repo(CompetitiveRepository $repo): array {
        $sum = $repo->summary();
        $by = $sum['by_type'] ?? [];

        $rules = [
            'updated_at' => time(),
            'thresholds' => [
                // якщо 40%+ сигналів конкурентів мають soft CTA на problem-сторінках — дозволяємо пропозицію CTA
                'problem' => 0.40,
                'info'    => 0.70, // info-сторінки зазвичай не мають CTA; поріг високий, щоб майже завжди було "ні"
                'unknown' => 0.50,
            ],
            'observed' => $by,
        ];

        update_option(self::OPTION_KEY, $rules, false);
        return $rules;
    }

    public static function get(): array {
        $r = get_option(self::OPTION_KEY);
        return is_array($r) ? $r : [
            'updated_at' => 0,
            'thresholds' => ['problem' => 0.40, 'info' => 0.70, 'unknown' => 0.50],
            'observed' => [],
        ];
    }

    public static function allow_soft_cta_for(string $page_type): bool {
        $rules = self::get();
        $page_type = $page_type ?: 'unknown';
        $th = (float) (($rules['thresholds'][$page_type] ?? 0.50));
        $obs = $rules['observed'][$page_type]['pct'] ?? null;
        if ($obs === null) {
            // якщо даних немає — дозволяємо лише для problem (обережно), для info — ні.
            return $page_type === 'problem';
        }
        return ((float)$obs) >= $th;
    }
}
