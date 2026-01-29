<?php
declare(strict_types=1);

namespace SEOJusAI\Modules;

use SEOJusAI\Core\Contracts\ModuleInterface;
use SEOJusAI\Core\Kernel;
use SEOJusAI\GSC\GSCClient;
use SEOJusAI\GSC\GscSnapshot;

defined('ABSPATH') || exit;

/**
 * GscIngestionModule
 *
 * Фонове оновлення GSC снапшотів (Service Account).
 *
 * Примітка: якщо Service Account не має доступу або properties недоступні,
 * job тихо no-op.
 */
final class GscIngestionModule implements ModuleInterface {

    private const CRON_HOOK = 'seojusai_gsc_refresh';

    public function get_slug(): string {
        return 'gsc_ingest';
    }

    public function register(Kernel $kernel): void {
        $kernel->register_module($this->get_slug(), $this);
    }

    public function init(Kernel $kernel): void {
        add_action('init', [self::class, 'schedule'], 30);
        add_action(self::CRON_HOOK, [self::class, 'refresh_snapshot']);
    }

    public static function schedule(): void {
        if (!wp_next_scheduled(self::CRON_HOOK)) {
            wp_schedule_event(time() + 420, 'hourly', self::CRON_HOOK);
        }
    }

    public static function refresh_snapshot(): void {
        $client = new GSCClient();
        if (!$client->is_connected()) {
            return;
        }

        $sites = $client->list_properties();
        if (empty($sites)) {
            return;
        }

        $site = (string) $sites[0];
        $data = $client->get_search_analytics($site);
        if (empty($data)) {
            return;
        }
        GscSnapshot::save($site, $data);
    }
}
