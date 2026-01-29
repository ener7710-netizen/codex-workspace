<?php
declare(strict_types=1);

namespace SEOJusAI\Capabilities;

defined('ABSPATH') || exit;

/**
 * CapabilityMap
 *
 * Єдине джерело істини для прав доступу SEOJusAI (2026).
 */
final class CapabilityMap {

    public const VIEW_REPORTS      = 'seojusai_view_reports';
    public const RUN_ANALYSIS      = 'seojusai_run_analysis';
    public const VIEW_QUEUE        = 'seojusai_view_queue';
    public const RUN_AUTOPILOT     = 'seojusai_run_autopilot';
    public const APPLY_CHANGES     = 'seojusai_apply_changes';
    public const APPROVE_CHANGES   = 'seojusai_approve_changes';

    public const MANAGE_REDIRECTS  = 'seojusai_manage_redirects';
    public const MANAGE_SITEMAP    = 'seojusai_manage_sitemap';
    public const MANAGE_SCHEMA     = 'seojusai_manage_schema';
    public const MANAGE_MODULES    = 'seojusai_manage_modules';
    public const MANAGE_SETTINGS   = 'seojusai_manage_settings';
    public const MANAGE_FEATURES   = 'seojusai_manage_features';

    /**
     * @return string[]
     */
    public static function all(): array {
        return [
            self::VIEW_REPORTS,
            self::RUN_ANALYSIS,
            self::VIEW_QUEUE,
            self::RUN_AUTOPILOT,
            self::APPLY_CHANGES,
            self::APPROVE_CHANGES,
            self::MANAGE_REDIRECTS,
            self::MANAGE_SITEMAP,
            self::MANAGE_SCHEMA,
            self::MANAGE_MODULES,
            self::MANAGE_SETTINGS,
            self::MANAGE_FEATURES,
        ];
    }
}
