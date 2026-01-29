<?php
declare(strict_types=1);

namespace SEOJusAI\AI\Engine;

defined('ABSPATH') || exit;

trait ValidationTrait {

    protected static function validate_facts(array $facts): array {

        $analysis = [];
        $tasks    = [];
        $score    = 100;

        /* ===============================
         * H1
         * =============================== */
        $h1 = $facts['headings']['h1'] ?? [];
        $h1_count = is_array($h1) ? count($h1) : 0;

        if ($h1_count === 0) {
            $analysis[] = self::issue('h1_missing', 'bad', '❌ H1 відсутній у HTML контенті сторінки.');
            $score -= 15;
        } elseif ($h1_count > 1) {
            $analysis[] = self::issue('h1_multiple', 'warning', '⚠️ На сторінці більше одного H1.');
            $score -= 5;
        } else {
            $analysis[] = self::issue('h1_ok', 'good', '✓ H1 присутній і унікальний.');
        }

        /* ===============================
         * CONTENT VOLUME
         * =============================== */
        $wc = (int) ($facts['content']['word_count'] ?? 0);

        if ($wc < 300) {
            $analysis[] = self::issue('thin_content', 'bad', '❌ Дуже малий обсяг тексту (Thin Content).');
            $score -= 15;
        } elseif ($wc < 800) {
            $analysis[] = self::issue('content_ok', 'good', '✓ Обсяг тексту достатній для сторінки послуги.');
        } else {
            $analysis[] = self::issue('content_rich', 'good', '✓ Глибокий контент (FAQ / кейси / пояснення).');
        }

        /* ===============================
         * IMAGES / ALT
         * =============================== */
        $img_total = (int) ($facts['images']['total'] ?? 0);
        $img_noalt = (int) ($facts['images']['missing_alt'] ?? 0);

        if ($img_total > 0 && $img_noalt > 0) {
            $analysis[] = self::issue(
                'img_alt_missing',
                'bad',
                "❌ Відсутній ALT у {$img_noalt} із {$img_total} зображень."
            );
            $tasks[] = self::task('Додати ALT-описи до зображень', 'high', true);
            $score -= min(10, $img_noalt * 2);
        } else {
            $analysis[] = self::issue('img_alt_ok', 'good', '✓ Усі зображення мають ALT.');
        }

        /* ===============================
         * CONTACTS (GLOBAL)
         * =============================== */
        $phones = $facts['conversion']['phones'] ?? [];
        $forms  = (int) ($facts['conversion']['forms'] ?? 0);
        $msgs   = $facts['conversion']['messengers'] ?? [];

        if (empty($phones) && $forms === 0 && empty($msgs)) {
            $analysis[] = self::issue(
                'contact_missing',
                'warning',
                '⚠️ Не знайдено телефону, форм або месенджерів.'
            );
            $tasks[] = self::task('Додати контактні дані (header/footer/popup)', 'high', false);
            $score -= 10;
        } else {
            $analysis[] = self::issue('contact_ok', 'good', '✓ Контактні дані присутні.');
        }

        /* ===============================
         * STRUCTURE BLOCKS
         * =============================== */
        $blocks = $facts['blocks'] ?? [];

        $required_blocks = [
            'prices_table'   => 'Таблиця цін',
            'documents_list' => 'Список документів',
            'steps_list'     => 'Алгоритм дій',
            'faq_block'      => 'FAQ блок',
            'cases_block'    => 'Кейси / судова практика',
        ];

        foreach ($required_blocks as $key => $label) {
            if (empty($blocks[$key])) {
                $analysis[] = self::issue(
                    $key . '_missing',
                    'warning',
                    "⚠️ Відсутній блок: {$label}."
                );
                $score -= 5;
            }
        }

        /* ===============================
         * SCHEMA — ATTORNEY / LEGALSERVICE
         * =============================== */
        $schema = $facts['schema_data']['Attorney'] ?? [];

        if (empty($schema['found'])) {
            $analysis[] = self::issue(
                'schema_attorney_missing',
                'bad',
                '❌ Відсутня Schema Attorney / LegalService.'
            );
            $tasks[] = self::task('Додати Schema Attorney + LocalBusiness', 'high', true);
            $score -= 20;
        } else {

            if (empty($schema['has_address'])) {
                $analysis[] = self::issue(
                    'schema_address_missing',
                    'warning',
                    '⚠️ У Schema не вказано адресу.'
                );
                $score -= 5;
            }

            if (empty($schema['has_priceRange'])) {
                $analysis[] = self::issue(
                    'schema_price_missing',
                    'warning',
                    '⚠️ У Schema відсутній priceRange.'
                );
                $score -= 5;
            }

            if (empty($schema['has_rating'])) {
                $analysis[] = self::issue(
                    'schema_rating_missing',
                    'warning',
                    '⚠️ Відсутній AggregateRating.'
                );
                $score -= 5;
            }

            if (empty($schema['has_telephone'])) {
                $analysis[] = self::issue(
                    'schema_phone_missing',
                    'warning',
                    '⚠️ У Schema не вказано телефон.'
                );
                $score -= 3;
            }
        }

        /* ===============================
         * PERSON (E-E-A-T)
         * =============================== */
        $person = $facts['schema_data']['Person'] ?? [];

        if (empty($person['found'])) {
            $analysis[] = self::issue(
                'schema_person_missing',
                'warning',
                '⚠️ Відсутня Schema Person (профіль адвоката).'
            );
            $score -= 5;
        } else {
            if (empty($person['has_jobTitle'])) {
                $analysis[] = self::issue(
                    'person_job_missing',
                    'warning',
                    '⚠️ У Person не вказано jobTitle.'
                );
                $score -= 2;
            }
            if (empty($person['has_social_links'])) {
                $analysis[] = self::issue(
                    'person_social_missing',
                    'warning',
                    '⚠️ Відсутні social links (sameAs).'
                );
                $score -= 2;
            }
        }

        /* ===============================
         * FAQ SCHEMA
         * =============================== */
        $faq_block = (bool) ($blocks['faq_block'] ?? false);
        $faq_schema = $facts['schema_data']['FAQPage'] ?? [];

        if ($faq_block && empty($faq_schema['found'])) {
            $analysis[] = self::issue(
                'faq_schema_missing',
                'warning',
                '⚠️ FAQ є у контенті, але не розмічений JSON-LD.'
            );
            $score -= 5;
        }

        /* ===============================
         * LAW / GOV LINKS
         * =============================== */
        $gov_links = (int) ($facts['links']['external_gov'] ?? 0);

        if ($gov_links === 0) {
            $analysis[] = self::issue(
                'law_refs_missing',
                'warning',
                '⚠️ Відсутні посилання на zakon.rada.gov.ua або court.gov.ua.'
            );
            $score -= 5;
        }

        /* ===============================
         * LICENSE / ERAU
         * =============================== */
        if (empty($facts['license_found'])) {
            $analysis[] = self::issue(
                'license_missing',
                'warning',
                '⚠️ Не знайдено номер свідоцтва або посилання на ЄРАУ.'
            );
            $score -= 5;
        }

        /* ===============================
         * COMPLIANCE
         * =============================== */
        $compliance = $facts['compliance'] ?? [];

        if (empty($compliance['has_cookie_consent'])) {
            $analysis[] = self::issue(
                'cookie_consent_missing',
                'warning',
                '⚠️ Не виявлено cookie/consent банера.'
            );
            $score -= 5;
        }

        if (empty($compliance['data_processing_ag'])) {
            $analysis[] = self::issue(
                'data_processing_missing',
                'warning',
                '⚠️ Відсутня згода на обробку персональних даних.'
            );
            $score -= 5;
        }

        if (empty($compliance['official_language'])) {
            $analysis[] = self::issue(
                'lang_ua_missing',
                'warning',
                '⚠️ Відсутній lang="uk" (офіційна мова).'
            );
            $score -= 5;
        }

        /* ===============================
         * SCORE LIMITS
         * =============================== */
        if ($score < 0) $score = 0;
        if ($score > 100) $score = 100;

        return [
            'score'    => $score,
            'analysis' => $analysis,
            'tasks'    => $tasks,
        ];
    }

    /* ===============================
     * HELPERS
     * =============================== */

    protected static function issue(string $code, string $status, string $desc): array {
        return [
            'code'   => $code,
            'status' => $status,
            'desc'   => $desc,
        ];
    }

    protected static function task(string $action, string $priority = 'medium', bool $auto = false): array {
        return [
            'action'    => $action,
            'priority'  => $priority,
            'auto'      => $auto,
            'completed' => false,
        ];
    }
}
