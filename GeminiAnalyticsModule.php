<?php
declare(strict_types=1);

namespace SEOJusAI\Modules;

defined('ABSPATH') || exit;

use SEOJusAI\AI\Integrations\GeminiAnalyticsGateway;
use SEOJusAI\Core\Kernel;
use SEOJusAI\Core\Contracts\ModuleInterface;

/**
 * GeminiAnalyticsModule
 *
 * Регулярно оновлює "об'єктивний" аналітичний висновок Gemini
 * на основі снапшотів GA4+GSC.
 */
final class GeminiAnalyticsModule implements ModuleInterface {

    public function get_slug(): string {
        return 'gemini_analytics';
    }

    public function register(Kernel $kernel): void {
        // no-op
    }

    public function init(Kernel $kernel): void {
        add_action('init', [ $this, 'schedule' ]);
        add_action('seojusai/gemini_analytics/refresh', [ $this, 'refresh' ]);
    }

    public function schedule(): void {
        if (!wp_next_scheduled('seojusai/gemini_analytics/refresh')) {
            // Кожні 6 годин (баланс: свіжість/ліміти).
            wp_schedule_event(time() + 60, 'twicedaily', 'seojusai/gemini_analytics/refresh');
        }
    }

    public function refresh(): void {
        // best-effort: не ламаємо cron якщо Gemini не налаштовано.
        try {
            GeminiAnalyticsGateway::get_or_compute(30, true);
        } catch (\Throwable $e) {
            // noop
        }
    }
}
