<?php
declare(strict_types=1);

namespace SEOJusAI\Analyze;

defined('ABSPATH') || exit;

/**
 * PageRulesetValidator
 *
 * Переводит "сырые факты" в:
 * - analysis (good / warning / bad)
 * - tasks (что делать)
 *
 * НИКАКОГО AI.
 * Только правила.
 */
final class PageRulesetValidator {

    public static function validate(array $facts): array {

        $analysis = [];
        $tasks    = [];

        /* ============================================================
         * H1
         * ============================================================ */
        $h1_count = count($facts['headings']['h1'] ?? []);

        if ($h1_count === 0) {
            self::bad(
                $analysis,
                $tasks,
                'h1_missing',
                '❌ Відсутній H1 заголовок.',
                'Додати один H1, який точно відповідає пошуковому інтенду сторінки.'
            );
        } elseif ($h1_count > 1) {
            self::bad(
                $analysis,
                $tasks,
                'h1_multiple',
                '❌ На сторінці більше одного H1.',
                'Залишити тільки один H1, інші заголовки понизити до H2/H3.'
            );
        } else {
            self::good($analysis, 'h1_ok', '✅ H1 присутній та коректний.');
        }

        /* ============================================================
         * META DESCRIPTION
         * ============================================================ */
        if (empty($facts['meta']['meta_desc'])) {
            self::warning(
                $analysis,
                $tasks,
                'meta_desc_missing',
                '⚠️ Meta Description відсутній.',
                'Додати meta description з CTA та перевагами.'
            );
        } else {
            self::good($analysis, 'meta_desc_ok', '✅ Meta Description присутній.');
        }

        /* ============================================================
         * IMAGES / ALT
         * ============================================================ */
        $img_total = (int) ($facts['images']['total'] ?? 0);
        $img_alt_miss = (int) ($facts['images']['missing_alt'] ?? 0);

        if ($img_total > 0 && $img_alt_miss > 0) {
            self::warning(
                $analysis,
                $tasks,
                'img_alt_missing',
                "⚠️ Відсутній ALT у {$img_alt_miss} з {$img_total} зображень.",
                'Заповнити ALT для всіх зображень (описово, без спаму).'
            );
        } else {
            self::good($analysis, 'img_alt_ok', '✅ ALT у зображень коректний.');
        }

        /* ============================================================
         * CONTACTS / CONVERSION
         * ============================================================ */
        $has_phone = !empty($facts['conversion']['phones']);
        $has_form  = (int) ($facts['conversion']['forms'] ?? 0) > 0;

        if (!$has_phone && !$has_form) {
            self::warning(
                $analysis,
                $tasks,
                'contact_missing',
                '⚠️ Контактні дані не виявлені у контенті.',
                'Додати телефон або форму звʼязку у видимій частині сторінки.'
            );
        } else {
            self::good($analysis, 'contact_ok', '✅ Контактні дані присутні.');
        }

        /* ============================================================
         * YMYL / LAW REFERENCES
         * ============================================================ */
        if (empty($facts['content']['lsi_keywords'])) {
            self::warning(
                $analysis,
                $tasks,
                'law_refs_missing',
                '⚠️ У тексті відсутні згадки норм права.',
                'Додати згадки законів, кодексів або судової практики.'
            );
        } else {
            self::good($analysis, 'law_refs_ok', '✅ Присутні юридичні терміни.');
        }

        /* ============================================================
         * STRUCTURE BLOCKS
         * ============================================================ */
        self::block_check($analysis, $tasks, $facts, 'prices_table', 'Таблиця цін',
            'Додати таблицю цін або діапазон вартості послуг.'
        );

        self::block_check($analysis, $tasks, $facts, 'documents_list', 'Список документів',
            'Додати перелік документів, необхідних для початку справи.'
        );

        self::block_check($analysis, $tasks, $facts, 'faq_block', 'FAQ блок',
            'Додати блок питань і відповідей (FAQ).'
        );

        self::block_check($analysis, $tasks, $facts, 'cases_block', 'Кейси / практика',
            'Додати опис виграних справ або посилання на судову практику.'
        );

        /* ============================================================
         * SCHEMA
         * ============================================================ */
        $schema = $facts['schema_data'] ?? [];

        if (empty($schema)) {
            self::warning(
                $analysis,
                $tasks,
                'schema_missing',
                '⚠️ Schema.org не виявлена.',
                'Додати розмітку Attorney / LegalService / FAQPage.'
            );
        } else {
            self::good($analysis, 'schema_present', '✅ Schema.org присутня.');
        }

        /* ============================================================
         * SCORE
         * ============================================================ */
        $score = self::score_from_analysis($analysis);

        return [
            'score'    => $score,
            'analysis' => $analysis,
            'tasks'    => $tasks,
        ];
    }

    /* ============================================================
     * HELPERS
     * ============================================================ */

    private static function good(array &$a, string $label, string $desc): void {
        $a[] = ['label' => $label, 'status' => 'good', 'desc' => $desc];
    }

    private static function warning(array &$a, array &$t, string $label, string $desc, string $task): void {
        $a[] = ['label' => $label, 'status' => 'warning', 'desc' => $desc];
        $t[] = self::task($task, 'medium');
    }

    private static function bad(array &$a, array &$t, string $label, string $desc, string $task): void {
        $a[] = ['label' => $label, 'status' => 'bad', 'desc' => $desc];
        $t[] = self::task($task, 'high');
    }

    private static function block_check(
        array &$a,
        array &$t,
        array $facts,
        string $key,
        string $title,
        string $task
    ): void {
        if (empty($facts['blocks'][$key])) {
            self::warning(
                $a,
                $t,
                "block_{$key}_missing",
                "⚠️ Відсутній блок: {$title}.",
                $task
            );
        } else {
            self::good($a, "block_{$key}_ok", "✅ Блок {$title} присутній.");
        }
    }

    private static function task(string $action, string $priority): array {
        return [
            'action'   => $action,
            'type'     => 'content',
            'priority' => $priority,
            'auto'     => false,
        ];
    }

    private static function score_from_analysis(array $analysis): int {
        $score = 100;
        foreach ($analysis as $a) {
            if ($a['status'] === 'bad') $score -= 15;
            if ($a['status'] === 'warning') $score -= 7;
        }
        return max(0, min(100, $score));
    }
}
