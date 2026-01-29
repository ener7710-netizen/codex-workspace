<?php
declare(strict_types=1);

namespace SEOJusAI\Analyze;

defined('ABSPATH') || exit;

/**
 * LocalSEOFactsProvider
 *
 * Витягує локальні SEO-сигнали (STRICT MODE):
 * - google_maps_embed: чи є вбудована карта Google
 * - nap_present: чи є NAP (name/address/phone) у видимому контенті
 * - city_in_h1_h2: чи є назва міста в H1/H2 (не лише у футері)
 * - office_photos: приблизна к-сть фото офісу/команди (по alt/class/src патернах)
 * - address_mentions: чи є схожі на адресу згадки
 * - geo_mentions: згадки міст/районів у тексті
 *
 * Ніяких припущень. Тільки те, що реально знайдено в HTML.
 */
final class LocalSEOFactsProvider {

    /**
     * @param string $html Повний HTML (front-end snapshot)
     * @param string $current_url Поточний URL (для контексту)
     */
    public static function extract(string $html, string $current_url): array {

        $clean_text = self::clean_text($html);

        $h1 = self::extract_headings($html, 1);
        $h2 = self::extract_headings($html, 2);

        $city = self::detect_primary_city($clean_text, $h1, $h2);

        $google_maps_embed = self::has_google_maps_embed($html);
        $nap_present       = self::detect_nap_present($html, $clean_text);
        $address_mentions  = self::extract_address_mentions($clean_text);
        $geo_mentions      = self::extract_geo_mentions($clean_text);

        $city_in_h1_h2 = false;
        if ($city !== '') {
            $city_in_h1_h2 = self::city_in_headings($city, $h1, $h2);
        } else {
            // якщо місто не визначили — перевіримо хоч Київ/Києві/Києва (як базовий кейс)
            $city_in_h1_h2 = self::city_in_headings_any(['Київ', 'Києві', 'Києва'], $h1, $h2);
        }

        $office_photos = self::count_office_photos($html);

        return [
            'google_maps_embed' => $google_maps_embed,
            'nap_present'       => $nap_present,
            'city_in_h1_h2'     => $city_in_h1_h2,
            'office_photos'     => $office_photos,

            // додаткові факти (для AI/Ruleset)
            'detected_city'     => $city,
            'address_mentions'  => $address_mentions,
            'geo_mentions'      => $geo_mentions,
        ];
    }

    /* ============================================================
     * Helpers
     * ============================================================ */

    private static function clean_text(string $html): string {
        $text = wp_strip_all_tags($html);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/\s+/u', ' ', (string)$text);
        return trim((string)$text);
    }

    /**
     * Витягує заголовки Hn у вигляді масиву рядків
     */
    private static function extract_headings(string $html, int $level): array {
        $out = [];
        $level = max(1, min(6, $level));

        if (preg_match_all('/<h' . $level . '[^>]*>(.*?)<\/h' . $level . '>/is', $html, $m)) {
            foreach ($m[1] as $raw) {
                $t = trim(wp_strip_all_tags((string)$raw));
                if ($t !== '') $out[] = $t;
            }
        }

        return $out;
    }

    /**
     * Вбудована Google карта:
     * - iframe google.com/maps
     * - embed?pb=
     * - maps.google.
     * - <a href="https://www.google.com/maps?...">
     */
    private static function has_google_maps_embed(string $html): bool {

        if (stripos($html, 'google.com/maps') !== false) return true;
        if (stripos($html, 'maps.google') !== false) return true;
        if (stripos($html, 'embed?pb=') !== false) return true;

        // інколи віджети кладуть data-атрибути
        if (preg_match('/data\-(?:map|maps)[^=]*=["\'][^"\']*(google\.com\/maps|maps\.google)[^"\']*["\']/i', $html)) {
            return true;
        }

        return false;
    }

    /**
     * NAP:
     * - телефон (є у вас вже окремо, але тут як локальний сигнал)
     * - слова "адрес", "вул.", "просп.", "Київ" + номер будинку
     * - блоки з класами address/nap/contacts/location
     */
    private static function detect_nap_present(string $html, string $clean_text): bool {

        $has_phone = (bool) preg_match('/\+?\d[\d\-\s\(\)]{8,}\d/u', $html);

        $has_address_word = (stripos($clean_text, 'адрес') !== false)
            || (stripos($clean_text, 'вул.') !== false)
            || (stripos($clean_text, 'вулиц') !== false)
            || (stripos($clean_text, 'просп') !== false)
            || (stripos($clean_text, 'бульв') !== false)
            || (stripos($clean_text, 'буд.') !== false);

        $has_address_pattern = (bool) preg_match(
            '/(?:вул\.|вулиця|просп\.|проспект|бульв\.|площа|провулок|наб\.|набережна)\s+[^\.,]{2,60}\s*(?:\d+[\/\-]?\d*)/iu',
            $clean_text
        );

        $has_contact_block = (bool) preg_match(
            '/class=["\'][^"\']*(?:contact|contacts|address|location|nap|footer\-contacts)[^"\']*["\']/i',
            $html
        );

        // мінімум: або (телефон + хоч щось про адресу), або явний контакт-блок з адресою
        if ($has_phone && ($has_address_word || $has_address_pattern)) return true;
        if ($has_contact_block && ($has_address_word || $has_address_pattern)) return true;

        return false;
    }

    /**
     * Спроба визначити основне місто:
     * - якщо в H1/H2 згадується місто зі списку (Київ, Львів, ...)
     * - або в тексті дуже часто згадується один з варіантів
     */
    private static function detect_primary_city(string $text, array $h1, array $h2): string {

        $cities = [
            'Київ', 'Львів', 'Одеса', 'Харків', 'Дніпро', 'Запоріжжя', 'Вінниця', 'Полтава', 'Чернігів',
            'Черкаси', 'Івано-Франківськ', 'Тернопіль', 'Рівне', 'Житомир', 'Миколаїв', 'Херсон', 'Суми',
            'Ужгород', 'Луцьк', 'Кропивницький'
        ];

        $hay_h = implode(' ', array_merge($h1, $h2));
        foreach ($cities as $c) {
            if (mb_stripos($hay_h, $c) !== false) {
                return $c;
            }
        }

        // fallback: по частоті в тексті
        $best_city = '';
        $best_cnt  = 0;

        foreach ($cities as $c) {
            $cnt = preg_match_all('/\b' . preg_quote($c, '/') . '\b/iu', $text, $m);
            if ($cnt > $best_cnt) {
                $best_cnt  = $cnt;
                $best_city = $c;
            }
        }

        // щоб не схопити випадкову згадку
        if ($best_cnt >= 2) return $best_city;

        return '';
    }

    private static function city_in_headings(string $city, array $h1, array $h2): bool {
        $hay = implode(' ', array_merge($h1, $h2));
        return mb_stripos($hay, $city) !== false;
    }

    private static function city_in_headings_any(array $variants, array $h1, array $h2): bool {
        $hay = implode(' ', array_merge($h1, $h2));
        foreach ($variants as $v) {
            if (mb_stripos($hay, (string)$v) !== false) return true;
        }
        return false;
    }

    /**
     * Витягає згадки, схожі на адресу (для дебагу/AI)
     */
    private static function extract_address_mentions(string $text): array {

        $out = [];

        if (preg_match_all(
            '/(?:вул\.|вулиця|просп\.|проспект|бульв\.|площа|провулок|наб\.|набережна)\s+[^\.,]{2,60}\s*(?:\d+[\/\-]?\d*)/iu',
            $text,
            $m
        )) {
            foreach ($m[0] as $raw) {
                $t = trim((string)$raw);
                if ($t !== '') $out[] = $t;
            }
        }

        // обмежимо, щоб не роздувати payload
        $out = array_values(array_unique($out));
        if (count($out) > 10) $out = array_slice($out, 0, 10);

        return $out;
    }

    /**
     * Гео-згадки: місто/райони/метро (мінімально, без словників на 1000 рядків)
     */
    private static function extract_geo_mentions(string $text): array {

        $patterns = [
            // базові міста/відмінки (можна розширити)
            '/\bКиїв(?:і|у|а|ом)?\b/iu',
            '/\bЛьвів(?:і|у|а|ом)?\b/iu',
            '/\bОдес(?:а|і|у|ою)\b/iu',
            '/\bХарків(?:і|у|а|ом)?\b/iu',
            '/\bДніпр(?:о|і|у|ом)\b/iu',

            // метро Києва як сильний локальний маркер
            '/\b(метро|м\.)\s*(Хрещатик|Палац\s+Спорту|Либідська|Позняки|Осокорки|Теремки|Печерська|Арсенальна)\b/iu',

            // райони Києва (мінімальний набір)
            '/\b(Печерськ|Поділ|Оболонь|Троєщина|Солом[\'’]?янка|Дарниця|Голосіїв)\b/iu',
        ];

        $found = [];

        foreach ($patterns as $p) {
            if (preg_match_all($p, $text, $m)) {
                foreach ($m[0] as $hit) {
                    $t = trim((string)$hit);
                    if ($t !== '') $found[] = $t;
                }
            }
        }

        $found = array_values(array_unique($found));
        if (count($found) > 20) $found = array_slice($found, 0, 20);

        return $found;
    }

    /**
     * Фото офісу/команди:
     * - alt містить "офіс", "команда", "адвокат", "юрист", "кабінет"
     * - class містить office/team/staff
     * - src містить office/team
     *
     * Це НЕ гарантує, що фото "реальне", але є чіткий сигнал.
     */
    private static function count_office_photos(string $html): int {

        if (!preg_match_all('/<img[^>]+>/i', $html, $imgs)) {
            return 0;
        }

        $count = 0;

        foreach ($imgs[0] as $tag) {
            $tag_l = strtolower((string)$tag);

            $ok = false;

            // alt
            if (preg_match('/alt=["\']([^"\']*)["\']/i', (string)$tag, $m)) {
                $alt = mb_strtolower(trim((string)$m[1]));
                if ($alt !== '') {
                    if (
                        str_contains($alt, 'офіс') ||
                        str_contains($alt, 'команд') ||
                        str_contains($alt, 'адвокат') ||
                        str_contains($alt, 'юрист') ||
                        str_contains($alt, 'кабінет') ||
                        str_contains($alt, 'law firm') ||
                        str_contains($alt, 'office') ||
                        str_contains($alt, 'team')
                    ) {
                        $ok = true;
                    }
                }
            }

            // class / src патерни
            if (!$ok) {
                if (
                    str_contains($tag_l, 'class=') &&
                    (str_contains($tag_l, 'office') || str_contains($tag_l, 'team') || str_contains($tag_l, 'staff'))
                ) {
                    $ok = true;
                }
            }

            if (!$ok) {
                if (
                    str_contains($tag_l, 'src=') &&
                    (str_contains($tag_l, '/office') || str_contains($tag_l, '/team') || str_contains($tag_l, '/staff'))
                ) {
                    $ok = true;
                }
            }

            if ($ok) $count++;
        }

        return $count;
    }
}
