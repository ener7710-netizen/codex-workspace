<?php
declare(strict_types=1);

namespace SEOJusAI\Compat;

defined('ABSPATH') || exit;

final class SeoEnvironmentDetector {

    public static function is_rank_math_active(): bool {
        return defined('RANK_MATH_VERSION') || class_exists('RankMath\\Helper') || class_exists('RankMath\\RankMath');
    }

    public static function is_yoast_active(): bool {
        return defined('WPSEO_VERSION') || class_exists('WPSEO_Frontend') || function_exists('wpseo_init');
    }

    public static function is_aioseo_active(): bool {
        return defined('AIOSEO_VERSION') || class_exists('AIOSEO\\Plugin\\AIOSEO') || class_exists('All_in_One_SEO_Pack');
    }

    public static function is_seopress_active(): bool {
        return defined('SEOPRESS_VERSION') || function_exists('seopress_init') || class_exists('SEOPress\\Init');
    }

    public static function is_any_seo_active(): bool {
        return self::is_rank_math_active() || self::is_yoast_active() || self::is_aioseo_active() || self::is_seopress_active();
    }

    public static function should_disable_frontend_emitting(): bool {
        return self::is_any_seo_active();
    }
}
