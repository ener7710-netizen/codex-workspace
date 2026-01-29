<?php
declare(strict_types=1);

namespace SEOJusAI\AI\Engine;

defined('ABSPATH') || exit;

trait SchemaTrait {

    private static function extract_schema_data(\DOMXPath $xp): array {
        $scripts = $xp->query('//script[@type="application/ld+json"]');
        $items = [];

        if ($scripts) {
            foreach ($scripts as $s) {
                $raw = trim((string) $s->textContent);
                if ($raw === '') continue;

                $decoded = self::safe_json_decode_loose($raw);
                if ($decoded === null) continue;

                if (is_array($decoded)) {
                    $items = array_merge($items, self::flatten_jsonld($decoded));
                } elseif (is_object($decoded)) {
                    $items = array_merge($items, self::flatten_jsonld((array) $decoded));
                }
            }
        }

        $types_found = self::collect_schema_types($items);

        $attorney = self::schema_validate_attorney_like($items, ['Attorney', 'LegalService', 'ProfessionalService', 'LocalBusiness', 'Organization']);
        $person   = self::schema_validate_person($items);
        $faq      = self::schema_validate_faqpage($items);
        $bread    = self::schema_validate_breadcrumbs($items);
        $review   = self::schema_validate_reviews_rating($items);

        return [
            'types' => $types_found,
            'Attorney' => $attorney,
            'Person'   => $person,
            'FAQPage'  => $faq,
            'BreadcrumbList' => $bread,
            'Review'   => $review,
        ];
    }

    private static function safe_json_decode_loose(string $raw) {
        $raw = trim($raw);
        if ($raw === '') return null;

        $json = json_decode($raw, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            return $json;
        }

        $fixed = preg_replace('/,\s*}/u', '}', $raw);
        $fixed = preg_replace('/,\s*]/u', ']', (string) $fixed);

        $json2 = json_decode((string) $fixed, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            return $json2;
        }

        return null;
    }

    private static function flatten_jsonld($decoded): array {
        $out = [];
        if (!is_array($decoded)) return $out;

        if (array_keys($decoded) === range(0, count($decoded) - 1)) {
            foreach ($decoded as $v) {
                if (is_array($v)) $out = array_merge($out, self::flatten_jsonld($v));
                elseif (is_object($v)) $out = array_merge($out, self::flatten_jsonld((array) $v));
            }
            return $out;
        }

        if (isset($decoded['@graph']) && is_array($decoded['@graph'])) {
            foreach ($decoded['@graph'] as $g) {
                if (is_array($g)) $out[] = $g;
                elseif (is_object($g)) $out[] = (array) $g;
            }
        }

        if (isset($decoded['@type']) || isset($decoded['type'])) {
            $out[] = $decoded;
        }

        $nestedKeys = [
            'mainEntity', 'mainEntityOfPage',
            'itemReviewed', 'review', 'reviews',
            'offers', 'provider', 'author', 'publisher',
            'address', 'geo', 'hasMap',
            'founder', 'employee', 'employees',
            'aggregateRating',
        ];

        foreach ($nestedKeys as $k) {
            if (!isset($decoded[$k])) continue;

            $v = $decoded[$k];

            if (is_array($v)) $out = array_merge($out, self::flatten_jsonld($v));
            elseif (is_object($v)) $out = array_merge($out, self::flatten_jsonld((array) $v));
        }

        return $out;
    }

    private static function collect_schema_types(array $items): array {
        $types = [];
        foreach ($items as $it) {
            if (!is_array($it)) continue;
            $t = self::schema_get_types($it);
            foreach ($t as $one) $types[] = $one;
        }
        $types = array_values(array_unique(array_filter($types)));
        sort($types);
        return $types;
    }

    private static function schema_get_types(array $obj): array {
        $t = $obj['@type'] ?? ($obj['type'] ?? null);
        if ($t === null) return [];

        if (is_string($t)) return [trim($t)];
        if (is_array($t)) {
            $out = [];
            foreach ($t as $x) if (is_string($x) && trim($x) !== '') $out[] = trim($x);
            return $out;
        }
        return [];
    }

    private static function schema_has_any_type(array $obj, array $want): bool {
        $types = self::schema_get_types($obj);
        $wantL = array_map('strtolower', $want);

        foreach ($types as $t) {
            if (in_array(strtolower($t), $wantL, true)) return true;
        }
        return false;
    }

    private static function schema_validate_attorney_like(array $items, array $types): array {
        $has_address = false;
        $has_opening = false;
        $has_priceRange = false;
        $has_rating = false;
        $has_credential = false;
        $has_telephone = false;
        $has_geo = false;
        $has_sameAs = false;
        $has_hasMap = false;
        $has_image = false;

        $found = false;

        foreach ($items as $it) {
            if (!is_array($it)) continue;
            if (!self::schema_has_any_type($it, $types)) continue;

            $found = true;

            if (isset($it['address'])) {
                $addr = $it['address'];
                if (is_string($addr) && trim($addr) !== '') $has_address = true;
                if (is_array($addr)) {
                    $street = (string) ($addr['streetAddress'] ?? '');
                    $loc    = (string) ($addr['addressLocality'] ?? '');
                    $reg    = (string) ($addr['addressRegion'] ?? '');
                    if (trim($street . $loc . $reg) !== '') $has_address = true;
                }
            }

            if (!empty($it['openingHours']) || !empty($it['openingHoursSpecification'])) {
                $has_opening = true;
            }

            if (!empty($it['telephone']) || !empty($it['contactPoint'])) {
                $has_telephone = true;
            }

            if (!empty($it['priceRange'])) {
                $has_priceRange = true;
            }

            if (!empty($it['image']) || !empty($it['logo'])) {
                $has_image = true;
            }

            if (isset($it['geo']) && is_array($it['geo'])) {
                $lat = $it['geo']['latitude'] ?? '';
                $lng = $it['geo']['longitude'] ?? '';
                if ((string) $lat !== '' && (string) $lng !== '') $has_geo = true;
            }

            if (!empty($it['hasMap'])) {
                $has_hasMap = true;
            }

            if (!empty($it['sameAs'])) {
                $has_sameAs = true;
            }

            if (!empty($it['aggregateRating']) || !empty($it['ratingValue'])) {
                $has_rating = true;
            }

            if (!empty($it['hasCredential']) || !empty($it['credential'])) {
                $has_credential = true;
            }

            if (isset($it['offers'])) {
                $offers = $it['offers'];
                if (is_array($offers)) {
                    if (!empty($offers['price']) || !empty($offers['priceSpecification'])) {
                        $has_priceRange = true;
                    }
                    if (array_keys($offers) === range(0, count($offers) - 1)) {
                        foreach ($offers as $off) {
                            if (!is_array($off)) continue;
                            if (!empty($off['price']) || !empty($off['priceSpecification'])) $has_priceRange = true;
                        }
                    }
                }
            }
        }

        return [
            'found'            => (bool) $found,
            'has_address'      => (bool) $has_address,
            'has_openingHours' => (bool) $has_opening,
            'has_priceRange'   => (bool) $has_priceRange,
            'has_rating'       => (bool) $has_rating,
            'has_credential'   => (bool) $has_credential,
            'has_telephone'    => (bool) $has_telephone,
            'has_geo'          => (bool) $has_geo,
            'has_hasMap'       => (bool) $has_hasMap,
            'has_sameAs'       => (bool) $has_sameAs,
            'has_image'        => (bool) $has_image,
        ];
    }

    private static function schema_validate_person(array $items): array {
        $found = false;
        $has_alumni = false;
        $has_social = false;
        $has_job = false;
        $has_knows = false;

        foreach ($items as $it) {
            if (!is_array($it)) continue;
            if (!self::schema_has_any_type($it, ['Person'])) continue;

            $found = true;

            if (!empty($it['alumniOf'])) $has_alumni = true;
            if (!empty($it['jobTitle'])) $has_job = true;
            if (!empty($it['knowsAbout'])) $has_knows = true;

            $sameAs = $it['sameAs'] ?? null;
            if (is_array($sameAs) && count($sameAs) > 0) $has_social = true;
            if (is_string($sameAs) && trim($sameAs) !== '') $has_social = true;
        }

        return [
            'found'            => (bool) $found,
            'has_alumni'       => (bool) $has_alumni,
            'has_jobTitle'     => (bool) $has_job,
            'has_knowsAbout'   => (bool) $has_knows,
            'has_social_links' => (bool) $has_social,
        ];
    }

    private static function schema_validate_faqpage(array $items): array {
        $found = false;
        $questions = 0;

        foreach ($items as $it) {
            if (!is_array($it)) continue;
            if (!self::schema_has_any_type($it, ['FAQPage'])) continue;

            $found = true;

            if (isset($it['mainEntity'])) {
                $me = $it['mainEntity'];

                if (is_array($me)) {
                    if (array_keys($me) === range(0, count($me) - 1)) {
                        foreach ($me as $q) {
                            if (is_array($q)) {
                                if (self::schema_has_any_type($q, ['Question'])) $questions++;
                                elseif (!empty($q['name'])) $questions++;
                            }
                        }
                    } else {
                        if (self::schema_has_any_type($me, ['Question'])) $questions++;
                        elseif (!empty($me['name'])) $questions++;
                    }
                }
            }
        }

        return [
            'found'           => (bool) $found,
            'questions_count' => (int) $questions,
        ];
    }

    private static function schema_validate_breadcrumbs(array $items): bool {
        foreach ($items as $it) {
            if (!is_array($it)) continue;
            if (!self::schema_has_any_type($it, ['BreadcrumbList'])) continue;
            if (!empty($it['itemListElement'])) return true;
            return true;
        }
        return false;
    }

    private static function schema_validate_reviews_rating(array $items): array {
        $has_review = false;
        $has_aggregate = false;
        $reviews_count = 0;

        foreach ($items as $it) {
            if (!is_array($it)) continue;

            if (self::schema_has_any_type($it, ['Review'])) {
                $has_review = true;
                $reviews_count++;
            }

            if (!empty($it['aggregateRating'])) {
                $has_aggregate = true;
            }

            if (self::schema_has_any_type($it, ['AggregateRating'])) {
                $has_aggregate = true;
            }
        }

        return [
            'has_review'          => (bool) $has_review,
            'has_aggregateRating' => (bool) $has_aggregate,
            'reviews_count'       => (int) $reviews_count,
        ];
    }
}
