<?php
declare(strict_types=1);

namespace SEOJusAI\Analyze;

defined('ABSPATH') || exit;

final class PageTypeResolver
{
    /**
     * Головний метод
     */
    public static function resolve(array $facts, string $url): string
    {
        // 1. HOME
        if (self::is_home($url)) {
            return 'home';
        }

        // 2. CASES
        if (self::is_cases($facts, $url)) {
            return 'cases';
        }

        // 3. ABOUT
        if (self::is_about($facts, $url)) {
            return 'about';
        }

        // 4. CONTACT
        if (self::is_contact($facts, $url)) {
            return 'contact';
        }

        // 5. BLOG
        if (self::is_blog($facts, $url)) {
            return 'blog';
        }

        // 6. SERVICE (юридична послуга)
        if (self::is_service($facts)) {
            return 'service';
        }

        return 'other';
    }

    /* ============================================================
     * RULES
     * ============================================================ */

    private static function is_home(string $url): bool
    {
        return untrailingslashit($url) === untrailingslashit(home_url());
    }

    private static function is_cases(array $facts, string $url): bool
    {
        return
            str_contains($url, 'case') ||
            str_contains($url, 'справ') ||
            str_contains($url, 'кейси') ||
            ($facts['blocks']['cases_block'] ?? false);
    }

    private static function is_about(array $facts, string $url): bool
    {
        return
            str_contains($url, 'about') ||
            str_contains($url, 'про-нас') ||
            (
                empty($facts['blocks']['prices_table']) &&
                empty($facts['blocks']['faq_block']) &&
                $facts['license_found'] === true
            );
    }

    private static function is_contact(array $facts, string $url): bool
    {
        return
            str_contains($url, 'contact') ||
            str_contains($url, 'контакт') ||
            (
                count($facts['phones'] ?? []) > 0 &&
                ($facts['forms'] ?? 0) > 0 &&
                ($facts['word_count'] ?? 0) < 500
            );
    }

    private static function is_blog(array $facts, string $url): bool
    {
        return
            str_contains($url, '/blog') ||
            (
                ($facts['word_count'] ?? 0) > 1200 &&
                empty($facts['blocks']['prices_table']) &&
                empty($facts['blocks']['cases_block'])
            );
    }

    private static function is_service(array $facts): bool
    {
        return
            ($facts['word_count'] ?? 0) >= 800 &&
            (
                ($facts['blocks']['prices_table'] ?? false) ||
                ($facts['blocks']['documents_list'] ?? false) ||
                ($facts['blocks']['steps_list'] ?? false)
            );
    }
}
