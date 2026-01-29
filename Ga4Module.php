<?php
declare(strict_types=1);

namespace SEOJusAI\Modules;

use SEOJusAI\Core\Contracts\ModuleInterface;
use SEOJusAI\Core\Kernel;
use SEOJusAI\GA4\GA4Client;
use SEOJusAI\GA4\Ga4Snapshot;

defined('ABSPATH') || exit;

/**
 * Ga4Module
 *
 * Додає GA4 (Service Account) як джерело даних:
 * - WP-Cron job для зняття снапшотів
 * - Нічого не змінює у вже існуючих модулях (аддитивно)
 */
final class Ga4Module implements ModuleInterface {

    private const CRON_HOOK = 'seojusai_ga4_refresh';

    public function get_slug(): string {
        return 'ga4';
    }

    public function register(Kernel $kernel): void {
        $kernel->register_module($this->get_slug(), $this);
    }

    public function init(Kernel $kernel): void {
        // Schedule after init to ensure WP Cron is available.
        add_action('init', [self::class, 'schedule'], 30);
        add_action(self::CRON_HOOK, [self::class, 'refresh_snapshot']);
    }

    public static function schedule(): void {
        // Run hourly; WordPress provides 'hourly'.
        if (!wp_next_scheduled(self::CRON_HOOK)) {
            wp_schedule_event(time() + 300, 'hourly', self::CRON_HOOK);
        }
    }

    public static function refresh_snapshot(): void {
        $client = new GA4Client();
        if (!$client->is_connected()) {
            return;
        }

        $days = 30;
        $overview = $client->get_overview($days);
        $pages = $client->get_pages($days, 500);

        if (empty($overview) && empty($pages)) {
            return;
        }

        Ga4Snapshot::save([
            'days'     => $days,
            'overview' => $overview,
            'pages'    => $pages,
        ]);
    }
}
