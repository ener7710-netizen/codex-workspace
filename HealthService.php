<?php
declare(strict_types=1);

namespace SEOJusAI\Health;

use SEOJusAI\Safety\SafeMode;
use SEOJusAI\Capabilities\CapabilityMap;

defined('ABSPATH') || exit;

final class HealthService {

    /**
     * @return array<int,array<string,mixed>>
     */
    public static function checks(): array {
        global $wpdb;

        $checks = [];

        // WordPress / PHP versions (informational)
        $checks[] = [
            'key' => 'env_versions',
            'title' => 'Версії середовища',
            'status' => 'info',
            'message' => sprintf('WordPress: %s · PHP: %s', get_bloginfo('version'), PHP_VERSION),
            'details' => [],
        ];

        // REST routes present
        $has_routes = false;
        $routes_cnt = 0;
        if (function_exists('rest_get_server')) {
            $server = rest_get_server();
            $routes = $server ? $server->get_routes() : [];
            if (is_array($routes)) {
                foreach (array_keys($routes) as $route) {
                    if (is_string($route) && strpos($route, '/seojusai/v1') !== false) {
                        $has_routes = true;
                        $routes_cnt++;
                    }
                }
            }
        }
        $checks[] = [
            'key' => 'rest_routes',
            'title' => 'REST API SEOJusAI',
            'status' => $has_routes ? 'ok' : 'fail',
            'message' => $has_routes ? ('Зареєстровано маршрутів: ' . (string)$routes_cnt) : 'Маршрути не знайдено. Перевірте ініціалізацію RestKernel.',
            'details' => [],
        ];

        // Database tables
        $required = self::required_tables();
        $missing = [];
        foreach ($required as $t) {
            $like = $wpdb->esc_like($wpdb->prefix . $t);
            $exists = $wpdb->get_var("SHOW TABLES LIKE '{$like}'");
            if (empty($exists)) {
                $missing[] = $wpdb->prefix . $t;
            }
        }
        $checks[] = [
            'key' => 'db_tables',
            'title' => 'Таблиці БД',
            'status' => empty($missing) ? 'ok' : 'fail',
            'message' => empty($missing) ? 'Усі необхідні таблиці присутні.' : ('Відсутні таблиці: ' . implode(', ', $missing)),
            'details' => [
                'required' => array_map(fn($t)=>$wpdb->prefix.$t, $required),
            ],
        ];

        // Action Scheduler availability
        $as_ok = function_exists('as_enqueue_async_action') || class_exists('ActionScheduler');
        $checks[] = [
            'key' => 'action_scheduler',
            'title' => 'Action Scheduler',
            'status' => $as_ok ? 'ok' : 'warn',
            'message' => $as_ok ? 'Доступний.' : 'Не виявлено. Черги можуть працювати лише через WP-Cron. Рекомендовано встановити Action Scheduler.',
            'details' => [],
        ];

        // WP-Cron
        $cron_disabled = defined('DISABLE_WP_CRON') && DISABLE_WP_CRON;
        $checks[] = [
            'key' => 'wp_cron',
            'title' => 'WP-Cron',
            'status' => $cron_disabled ? 'warn' : 'ok',
            'message' => $cron_disabled ? 'DISABLE_WP_CRON увімкнено. Переконайтеся, що налаштований системний cron.' : 'Увімкнено.',
            'details' => [],
        ];

        // Safe mode
        $safe = class_exists(SafeMode::class) ? SafeMode::is_enabled() : false;
        $checks[] = [
            'key' => 'safe_mode',
            'title' => 'Safe Mode',
            'status' => $safe ? 'warn' : 'ok',
            'message' => $safe ? 'Увімкнено: застосування змін заблоковано.' : 'Вимкнено.',
            'details' => [],
        ];

        // Object cache
        $obj = function_exists('wp_using_ext_object_cache') && wp_using_ext_object_cache();
        $checks[] = [
            'key' => 'object_cache',
            'title' => 'Object Cache',
            'status' => $obj ? 'ok' : 'info',
            'message' => $obj ? 'Зовнішній object cache активний.' : 'Зовнішній object cache не виявлено (не критично).',
            'details' => [],
        ];

        // Capabilities present (informational)
        $checks[] = [
            'key' => 'caps',
            'title' => 'Модель доступів',
            'status' => 'info',
            'message' => 'Використовуються capabilities: ' . esc_html(CapabilityMap::MANAGE_SETTINGS) . ', ' . esc_html(CapabilityMap::MANAGE_FEATURES),
            'details' => [],
        ];

        return $checks;
    }

    /**
     * @return string[]
     */
    public static function required_tables(): array {
        // must match Database\Tables
        return [
            'seojusai_snapshots',
            'seojusai_tasks',
            'seojusai_explanations',
            'seojusai_knowledge',
            'seojusai_trace',
            'seojusai_redirects',
            'seojusai_404',
            'seojusai_vectors',
            'seojusai_bulk_jobs',
            'seojusai_locks',
            'seojusai_learning',
            'seojusai_impact',
        ];
    }

    public static function summary_status(): string {
        $checks = self::checks();
        $fail = 0; $warn = 0;
        foreach ($checks as $c) {
            if (($c['status'] ?? '') === 'fail') $fail++;
            if (($c['status'] ?? '') === 'warn') $warn++;
        }
        if ($fail > 0) return 'fail';
        if ($warn > 0) return 'warn';
        return 'ok';
    }
}
