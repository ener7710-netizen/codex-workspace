<?php
declare(strict_types=1);

namespace SEOJusAI\Autopilot;

defined('ABSPATH') || exit;

/**
 * AutopilotWorkerIdentity
 *
 * Deterministic worker identity (stable per-site).
 * @invariant No random UUID per request.
 */
final class AutopilotWorkerIdentity
{
    public static function id(): string
    {
        $home = function_exists('home_url') ? (string) home_url('/') : 'unknown';
        $ver  = defined('SEOJUSAI_VERSION') ? (string) SEOJUSAI_VERSION : '0';
        return hash('sha256', $home . '|seojusai|autopilot|' . $ver);
    }
}
