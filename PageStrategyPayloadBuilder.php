<?php
declare(strict_types=1);

namespace SEOJusAI\Analyze;

defined('ABSPATH') || exit;

final class PageStrategyPayloadBuilder
{
    /**
     * Побудувати фінальний payload для AI (STRICT SOURCE)
     */
    public static function build(
        array $facts,
        array $ruleset_result,
        array $score_result,
        string $page_type
    ): array {

        return [
            'meta' => [
                'page_type' => $page_type,
                'generated_at' => time(),
                'strict_source' => true,
            ],

            /**
             * 1️⃣ ФАКТИ (HTML — ЄДИНЕ ДЖЕРЕЛО)
             */
            'facts' => [
                'title'       => $facts['title'] ?? '',
                'meta_desc'   => $facts['meta_desc'] ?? '',
                'h1'          => $facts['h1'] ?? [],
                'h2'          => $facts['h2'] ?? [],
                'h3'          => $facts['h3'] ?? [],

                'text_content' => $facts['text_content'] ?? '',
                'word_count'   => $facts['word_count'] ?? 0,

                'images' => [
                    'total'       => $facts['images']['total'] ?? 0,
                    'missing_alt' => $facts['images']['missing_alt'] ?? 0,
                ],

                'phones' => $facts['phones'] ?? [],
                'forms'  => $facts['forms'] ?? 0,

                'blocks' => $facts['blocks'] ?? [],

                'schema_data' => $facts['schema_data'] ?? [],

                'links' => [
                    'internal' => $facts['links']['internal'] ?? 0,
                    'external' => $facts['links']['external'] ?? 0,
                ],

                'external_links_gov' => $facts['external_links_gov'] ?? 0,
                'license_found'      => (bool) ($facts['license_found'] ?? false),
            ],

            /**
             * 2️⃣ ПРАВИЛА (ЩО ПОВИННО БУТИ)
             */
            'rules' => [
                'required_ok'      => array_keys($ruleset_result['required_ok'] ?? []),
                'required_missing' => array_keys($ruleset_result['required_missing'] ?? []),
                'optional_ok'      => array_keys($ruleset_result['optional_ok'] ?? []),
                'optional_missing' => array_keys($ruleset_result['optional_missing'] ?? []),
            ],

            /**
             * 3️⃣ ОБʼЄКТИВНА ОЦІНКА
             */
            'score' => [
                'value'      => $score_result['score'] ?? 0,
                'risk_level' => $score_result['risk_level'] ?? 'unknown',
                'priority'   => $score_result['priority'] ?? [],
            ],

            /**
             * 4️⃣ ОБМЕЖЕННЯ ДЛЯ AI
             */
            'ai_constraints' => [
                'forbidden' => [
                    'hallucinations',
                    'inventing_facts',
                    'using_external_knowledge',
                ],
                'instructions' => [
                    'Якщо даних немає — пиши: "❌ Дані відсутні у джерелі"',
                    'Усі дії формулюй як РЕКОМЕНДАЦІЇ',
                    'Посилайся ТІЛЬКИ на facts / rules / score',
                ],
            ],
        ];
    }
}
