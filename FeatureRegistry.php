<?php
declare(strict_types=1);

namespace SEOJusAI\Features;

defined('ABSPATH') || exit;

/**
 * FeatureRegistry
 * Єдине джерело істини для прапорців функцій (Feature Flags).
 */
final class FeatureRegistry {

    /**
     * @return FeatureFlag[]
     */
    public static function all(): array {
        return [
            new FeatureFlag(
                'editor_inline_internal_links_v1',
                'Inline: Внутрішні посилання (v1)',
                'Підказки та застосування внутрішніх посилань у редакторі (низький ризик).',
                'stable',
                true,
                '1.0.0'
            ),
            new FeatureFlag(
                'editor_inline_headings_keywords_v1',
                'Inline: Заголовки та ключові фрази (v1)',
                'Підказки по заголовках/ключах у редакторі (ризик low/medium).',
                'stable',
                true,
                '1.0.0'
            ),
            new FeatureFlag(
                'editor_diff_preview_v1',
                'Inline: Diff Preview (v1)',
                'Показ «було/стане» перед застосуванням змін.',
                'stable',
                true,
                '1.0.0'
            ),
            new FeatureFlag(
                'bulk_job_summary_v1',
                'Bulk: Підсумок job перед підтвердженням',
                'Агрегований ROI/Impact/Risk та керування підтвердженням.',
                'stable',
                true,
                '1.0.0'
            ),
new FeatureFlag(
    'autopilot_full_safe_mode_v1',
    'Autopilot: Full SAFE mode (v1)',
    'Дозволяє Autopilot у режимі FULL автоматично застосовувати лише allowlisted low-risk дії (зі снапшотом та логом).',
    'stable',
    false,
    '1.3.0'
),
            new FeatureFlag(
                'autopilot_experimental_scoring_v2',
                'Autopilot: Експериментальний скоринг (v2)',
                'Новий алгоритм оцінки пріоритетів. Вмикати лише на staging/тест.',
                'experimental',
                false,
                '1.2.0'
            ),
        ];
    }

    /**
     * @return array<string,bool>
     */
    public static function defaults(): array {
        $out = [];
        foreach (self::all() as $flag) {
            $out[$flag->key] = (bool)$flag->default;
        }
        return $out;
    }

    public static function exists(string $key): bool {
        foreach (self::all() as $flag) {
            if ($flag->key === $key) return true;
        }
        return false;
    }

    public static function get(string $key): ?FeatureFlag {
        foreach (self::all() as $flag) {
            if ($flag->key === $key) return $flag;
        }
        return null;
    }
}
