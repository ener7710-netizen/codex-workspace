<?php
declare(strict_types=1);

namespace SEOJusAI\AI\Engine;

defined('ABSPATH') || exit;

trait ParserTrait {

    public static function analyze_post(int $post_id): array {
        if ($post_id <= 0) {
            return self::empty_result('Некоректний post_id');
        }

        $url = get_permalink($post_id);
        if (!$url) {
            return self::empty_result('Не вдалося отримати URL сторінки');
        }

        $full_html = self::fetch_rendered_html($url);
        if ($full_html === '') {
            return self::empty_result('Не вдалося отримати HTML сторінки');
        }

        $facts = self::parse_dom_facts($url, $full_html);
        if (!is_array($facts) || empty($facts)) {
            return self::empty_result('Факти не зібрані');
        }

        $validated = self::validate_facts($facts);

        $analysis_data = [
            'post_id'    => $post_id,
            'url'        => $url,
            'facts'      => $facts,
            'analysis'   => (array) ($validated['analysis'] ?? []),
            'tasks'      => (array) ($validated['tasks'] ?? []),
            'score'      => (int) ($validated['score'] ?? 0),
            'updated_at' => current_time('mysql'),
            'mode'       => 'no_ai',
        ];

        self::store_analysis_data($post_id, $analysis_data);

        return [
            'ok'             => true,
            'post_id'        => $post_id,
            'score'          => $analysis_data['score'],
            'analysis'       => $analysis_data['analysis'],
            'tasks'          => $analysis_data['tasks'],
            'facts'          => $analysis_data['facts'],
            'schema_suggest' => '',
            'mode'           => 'no_ai',
        ];
    }

    private static function parse_dom_facts(string $url, string $full_html): array {
        $doc = new \DOMDocument();
        libxml_use_internal_errors(true);
        $doc->loadHTML('<?xml encoding="UTF-8">' . $full_html);
        libxml_clear_errors();

        $xpath = new \DOMXPath($doc);

        $title = self::extract_title($xpath);
        $meta_desc = self::extract_meta($xpath, 'description');

        $og_title = self::extract_meta_property($xpath, 'og:title');
        $og_image = self::extract_meta_property($xpath, 'og:image') !== '';

        $mainNode = self::detect_main_node($xpath);

        $content_html = $mainNode ? self::outer_html($mainNode) : '';
        $content_text = self::clean_text_from_node($mainNode ?: $doc->documentElement);

        $text_clip = self::mb_clip($content_text, 5000);
        $word_count = self::word_count($text_clip);
        $readability = self::compute_readability_ua_ru($text_clip);
        $lsi_keywords = self::has_lsi_markers($text_clip);

        $h1 = self::extract_headings_from_node($xpath, $mainNode, 1);
        $h2 = self::extract_headings_from_node($xpath, $mainNode, 2);
        $h3 = self::extract_headings_from_node($xpath, $mainNode, 3);

        $images = self::extract_images_stats_from_node($xpath, $mainNode);

        $phones = self::extract_phones($full_html);
        $forms_count = self::count_forms($xpath);
        $messengers = self::extract_messengers($full_html);
        $address = self::extract_address_like($full_html);
        $map_embed = self::has_google_maps_embed($full_html);

        $links = self::extract_links_stats_from_node($xpath, $mainNode);
        $external_gov = self::count_gov_links_from_node($xpath, $mainNode);

        $blocks = self::detect_blocks($content_html, $content_text, $full_html);

        $schema_data = self::extract_schema_data($xpath);

        $license_found = self::detect_license_markers($full_html);

        $compliance = self::detect_compliance($full_html, $xpath);

        return [
            'meta' => [
                'title'     => $title,
                'meta_desc' => $meta_desc,
                'og_title'  => $og_title,
                'og_image'  => $og_image,
            ],
            'headings' => [
                'h1' => $h1,
                'h2' => $h2,
                'h3' => $h3,
            ],
            'content' => [
                'text_content' => $text_clip,
                'word_count'   => $word_count,
                'readability'  => $readability,
                'lsi_keywords' => $lsi_keywords,
            ],
            'images' => $images,
            'conversion' => [
                'phones'     => $phones,
                'forms'      => $forms_count,
                'messengers' => $messengers,
                'address'    => $address,
                'map_embed'  => $map_embed,
            ],
            'blocks' => $blocks,
            'schema_data' => $schema_data,
            'links' => [
                'internal'     => (int) ($links['internal'] ?? 0),
                'external'     => (int) ($links['external'] ?? 0),
                'external_gov' => (int) $external_gov,
                'broken_links' => (int) ($links['broken'] ?? 0),
            ],
            'license_found' => (bool) $license_found,
            'compliance' => $compliance,
            'url' => $url,
        ];
    }

    private static function extract_title(\DOMXPath $xp): string {
        $n = $xp->query('//title')->item(0);
        return $n ? trim((string) $n->textContent) : '';
    }

    private static function extract_meta(\DOMXPath $xp, string $name): string {
        $q = '//meta[translate(@name,"ABCDEFGHIJKLMNOPQRSTUVWXYZ","abcdefghijklmnopqrstuvwxyz")="' . strtolower($name) . '"]/@content';
        $n = $xp->query($q)->item(0);
        return $n ? trim((string) $n->nodeValue) : '';
    }

    private static function extract_meta_property(\DOMXPath $xp, string $property): string {
        $q = '//meta[@property="' . $property . '"]/@content';
        $n = $xp->query($q)->item(0);
        return $n ? trim((string) $n->nodeValue) : '';
    }

    private static function detect_main_node(\DOMXPath $xp): ?\DOMNode {
        $n = $xp->query('//main')->item(0);
        if ($n) return $n;

        $candidates = [
            '//*[@class and contains(concat(" ", normalize-space(@class), " "), " entry-content ")]',
            '//*[@class and contains(concat(" ", normalize-space(@class), " "), " wp-site-blocks ")]',
            '//*[@id="content"]',
            '//*[@id="primary"]',
            '//article',
        ];

        foreach ($candidates as $q) {
            $n = $xp->query($q)->item(0);
            if ($n) return $n;
        }

        return null;
    }

    private static function outer_html(\DOMNode $node): string {
        $doc = $node->ownerDocument;
        if (!$doc) return '';
        return $doc->saveHTML($node) ?: '';
    }

    private static function clean_text_from_node(\DOMNode $node): string {
        $tmp = new \DOMDocument();
        libxml_use_internal_errors(true);
        $imported = $tmp->importNode($node, true);
        $tmp->appendChild($imported);
        libxml_clear_errors();

        $xp = new \DOMXPath($tmp);

        foreach (['//script', '//style', '//noscript'] as $q) {
            $list = $xp->query($q);
            if (!$list) continue;
            for ($i = $list->length - 1; $i >= 0; $i--) {
                $rm = $list->item($i);
                if ($rm && $rm->parentNode) $rm->parentNode->removeChild($rm);
            }
        }

        $text = $tmp->textContent ?: '';
        $text = wp_strip_all_tags($text);
        $text = preg_replace('/\s+/u', ' ', $text);
        return trim((string) $text);
    }

    private static function extract_headings_from_node(\DOMXPath $xp, ?\DOMNode $scope, int $level): array {
        $out = [];
        $nodes = $scope ? $xp->query('.//h' . $level, $scope) : $xp->query('//h' . $level);
        if (!$nodes) return [];
        foreach ($nodes as $n) {
            $t = trim(wp_strip_all_tags((string) $n->textContent));
            if ($t !== '') $out[] = $t;
        }
        return array_values(array_unique($out));
    }

    private static function extract_images_stats_from_node(\DOMXPath $xp, ?\DOMNode $scope): array {
        $total = 0;
        $missing = 0;

        $nodes = $scope ? $xp->query('.//img', $scope) : $xp->query('//img');
        if ($nodes) {
            foreach ($nodes as $img) {
                $total++;
                $alt = '';
                if ($img instanceof \DOMElement) {
                    $alt = trim((string) $img->getAttribute('alt'));
                }
                if ($alt === '') $missing++;
            }
        }

        $has_face = self::detect_lawyer_face_hint($scope ? self::outer_html($scope) : '');

        return [
            'total'           => (int) $total,
            'missing_alt'     => (int) $missing,
            'has_lawyer_face' => (bool) $has_face,
        ];
    }

    private static function detect_lawyer_face_hint(string $content_html): bool {
        if ($content_html === '') return false;
        $h = mb_strtolower($content_html);
        return (bool) (
            preg_match('/(адвокат|юрист|команда|наші юристи|партнер|керуючий партнер|фото адвоката)/iu', $h) ||
            preg_match('/(avatar|profile|team|lawyer|attorney)/iu', $h)
        );
    }

    private static function extract_links_stats_from_node(\DOMXPath $xp, ?\DOMNode $scope): array {
        $internal = 0;
        $external = 0;
        $broken   = 0;

        $site = home_url();
        $site_host = (string) wp_parse_url($site, PHP_URL_HOST);

        $nodes = $scope ? $xp->query('.//a[@href]', $scope) : $xp->query('//a[@href]');
        if ($nodes) {
            foreach ($nodes as $a) {
                if (!($a instanceof \DOMElement)) continue;
                $href = self::normalize_url((string) $a->getAttribute('href'));
                if ($href === '' || str_starts_with($href, '#') || str_starts_with(strtolower($href), 'javascript:')) continue;

                if (self::is_probably_broken_href($href)) $broken++;

                $host = (string) wp_parse_url($href, PHP_URL_HOST);
                if ($host === '') {
                    $internal++;
                    continue;
                }

                if ($site_host !== '' && $host === $site_host) $internal++;
                else $external++;
            }
        }

        return [
            'internal' => (int) $internal,
            'external' => (int) $external,
            'broken'   => (int) $broken,
        ];
    }

    private static function count_gov_links_from_node(\DOMXPath $xp, ?\DOMNode $scope): int {
        $count = 0;
        $nodes = $scope ? $xp->query('.//a[@href]', $scope) : $xp->query('//a[@href]');
        if (!$nodes) return 0;

        foreach ($nodes as $a) {
            if (!($a instanceof \DOMElement)) continue;
            $href = self::normalize_url((string) $a->getAttribute('href'));
            if ($href === '') continue;

            $h = strtolower($href);

            if (
                str_contains($h, 'rada.gov.ua') ||
                str_contains($h, 'zakon.rada.gov.ua') ||
                str_contains($h, 'court.gov.ua') ||
                str_contains($h, 'reyestr.court.gov.ua') ||
                preg_match('/\.gov\.ua\b/i', $h) ||
                preg_match('/\.court\.gov\.ua\b/i', $h)
            ) {
                $count++;
            }
        }

        return $count;
    }

    private static function extract_phones(string $html): array {
        $phones = [];

        preg_match_all('/(\+?380[\s\-]?\(?\d{2}\)?[\s\-]?\d{3}[\s\-]?\d{2}[\s\-]?\d{2})/u', $html, $m1);
        preg_match_all('/(\+?\d[\d\-\s\(\)]{8,}\d)/u', $html, $m2);

        $raw = array_merge($m1[1] ?? [], $m2[1] ?? []);
        foreach ($raw as $p) {
            $p = trim((string) $p);
            $p = preg_replace('/[^\d\+]/u', '', $p);
            if ($p === '') continue;

            $digits = preg_replace('/\D/u', '', $p);
            $len = strlen($digits);
            if ($len < 9 || $len > 15) continue;

            $phones[] = $p;
        }

        return array_values(array_unique($phones));
    }

    private static function count_forms(\DOMXPath $xp): int {
        $nodes = $xp->query('//form');
        return $nodes ? (int) $nodes->length : 0;
    }

    private static function extract_messengers(string $html): array {
        $out = [];
        $h = strtolower($html);

        if (str_contains($h, 't.me/') || str_contains($h, 'telegram.me')) $out[] = 'telegram';
        if (str_contains($h, 'viber://') || str_contains($h, 'vb.me') || str_contains($h, 'viber.com')) $out[] = 'viber';
        if (str_contains($h, 'wa.me/') || str_contains($h, 'whatsapp.com')) $out[] = 'whatsapp';
        if (str_contains($h, 'm.me/') || str_contains($h, 'messenger.com')) $out[] = 'messenger';

        return array_values(array_unique($out));
    }

    private static function extract_address_like(string $html): string {
        $text = wp_strip_all_tags($html);
        $text = preg_replace('/\s+/u', ' ', (string) $text);
        $text = trim((string) $text);

        if (preg_match('/(м\.\s*Київ|Київ)\s*,?\s*(вул\.|вулиця|просп\.|проспект|пл\.|площа)\s*[^,]{3,80}\d{1,5}[^,]{0,20}/iu', $text, $m)) {
            return trim((string) $m[0]);
        }

        return '';
    }

    private static function has_google_maps_embed(string $html): bool {
        $h = strtolower($html);
        return (bool) (
            str_contains($h, 'google.com/maps') ||
            str_contains($h, 'maps.google.com') ||
            str_contains($h, 'google_maps') ||
            str_contains($h, 'maps/embed')
        );
    }

    private static function detect_blocks(string $content_html, string $content_text, string $full_html): array {
        $h = strtolower($content_html);
        $t = mb_strtolower($content_text);
        $g = mb_strtolower(wp_strip_all_tags($full_html));

        $prices_table = false;
        if (str_contains($h, '<table')) {
            if (preg_match('/(\bгрн\b|₴|\$|€|\bціна\b|\bвартість\b|\bвід\s*\d+)/iu', $content_text)) {
                $prices_table = true;
            }
        }

        $steps_list = str_contains($h, '<ol');

        $documents_list = false;
        if (str_contains($h, '<ul')) {
            if (preg_match('/(паспорт|ідент|ідентифікац|код|заява|позов|свідоцтво|документ)/iu', $content_text)) {
                $documents_list = true;
            }
        }

        $faq_block = (bool) (
            str_contains($h, 'accordion') ||
            preg_match('/(часті питання|питання та відповіді|faq)/iu', $content_text)
        );

        $cases_block = (bool) (
            preg_match('/(кейс|успішн(і|о)\s+справ(и|а)|судов(а|і)\s+практик(а|и)|рішення\s+суду)/iu', $content_text) ||
            str_contains($h, 'reyestr.court.gov.ua') ||
            str_contains($h, 'court.gov.ua')
        );

        $license_block = (bool) preg_match('/(свідоцтв(о|а)\s*№|ліцензі(я|ї)|єрау|erau\.unba|unba\.org\.ua)/iu', $g);

        return [
            'prices_table'   => (bool) $prices_table,
            'steps_list'     => (bool) $steps_list,
            'documents_list' => (bool) $documents_list,
            'faq_block'      => (bool) $faq_block,
            'cases_block'    => (bool) $cases_block,
            'license_block'  => (bool) $license_block,
        ];
    }

    private static function detect_license_markers(string $html): bool {
        $t = mb_strtolower(wp_strip_all_tags($html));
        return (bool) preg_match('/(свідоцтв(о|а)\s*№|ліцензі(я|ї)|єрау|erau\.unba|unba\.org\.ua)/iu', $t);
    }

    private static function detect_compliance(string $html, \DOMXPath $xp): array {
        $t = mb_strtolower($html);

        $has_cookie = (bool) (
            str_contains($t, 'cookie') ||
            str_contains($t, 'gdpr') ||
            str_contains($t, 'consent') ||
            str_contains($t, 'complianz') ||
            str_contains($t, 'cookieyes')
        );

        $data_processing = false;
        $forms = $xp->query('//form');
        if ($forms) {
            foreach ($forms as $f) {
                $text = mb_strtolower(wp_strip_all_tags((string) $f->textContent));
                if (preg_match('/(згод(а|ен)|обробк(а|у)\s+персональн(их|і)\s+дан(их|і)|privacy policy|політик(а|и)\s+конфіденційності)/iu', $text)) {
                    $data_processing = true;
                    break;
                }
            }
        }

        $official_language = (bool) preg_match('/<html[^>]+lang=["\']uk["\']/iu', $html);

        return [
            'has_cookie_consent' => (bool) $has_cookie,
            'data_processing_ag' => (bool) $data_processing,
            'official_language'  => (bool) $official_language,
        ];
    }
}
