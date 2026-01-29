<?php
declare(strict_types=1);

namespace SEOJusAI\Core;

use wpdb;
use SEOJusAI\Core\EmergencyStop;

if ( ! defined('ABSPATH') ) {
	exit;
}

/**
 * HealthCheck — діагностика стану системи SEOJusAI
 *
 * ВАЖЛИВО:
 * - Жодних фаталів
 * - Повертає структурований стан для Dashboard/System
 * - Пояснює причини Degraded Mode
 */
final class HealthCheck {

	private Plugin $plugin;
	private ModuleRegistry $modules;

	public function __construct(Plugin $plugin) {
		$this->plugin  = $plugin;
		$this->modules = ModuleRegistry::instance();
	}

	/**
	 * Основний health snapshot.
	 *
	 * @return array<string,mixed>
	 */
	public function snapshot(): array {
		$checks = [];

		$checks['environment'] = $this->check_environment();
		$checks['wordpress']   = $this->check_wordpress();
		$checks['database']    = $this->check_database();
		$checks['rest']        = $this->check_rest();
		$checks['modules']     = $this->check_modules();

		$summary = $this->summarize($checks);

		return [
			'summary' => $summary,
			'checks'  => $checks,
		];
	}

	private function check_environment(): array {
		$items = [];

		$items[] = $this->item(
			'php_version',
			version_compare(PHP_VERSION, '8.2.0', '>='),
			sprintf(__('PHP версія: %s', 'seojusai'), PHP_VERSION),
			__('Потрібна PHP 8.2+ для стабільної роботи.', 'seojusai')
		);

		$items[] = $this->item(
			'openssl',
			extension_loaded('openssl'),
			__('OpenSSL доступний', 'seojusai'),
			__('OpenSSL потрібен для шифрування ключів (AES-256).', 'seojusai')
		);

		$items[] = $this->item(
			'curl_or_http',
			function_exists('wp_remote_post'),
			__('HTTP клієнт WordPress доступний', 'seojusai'),
			__('Потрібен wp_remote_post для викликів AI API.', 'seojusai')
		);

		return [
			'ok'    => $this->all_ok($items),
			'items' => $items,
		];
	}

	private function check_wordpress(): array {
		$items = [];

		$wp_version = (string) get_bloginfo('version');

		$items[] = $this->item(
			'wp_version',
			version_compare($wp_version, '6.4', '>='),
			sprintf(__('WordPress версія: %s', 'seojusai'), $wp_version),
			__('Потрібен WordPress 6.4+.', 'seojusai')
		);

		$items[] = $this->item(
			'permalinks',
			(bool) get_option('permalink_structure'),
			__('Постійні посилання увімкнено', 'seojusai'),
			__('Рекомендовано увімкнути постійні посилання для SEO.', 'seojusai')
		);

		return [
			'ok'    => $this->all_ok($items),
			'items' => $items,
		];
	}

	private function check_database(): array {
		global $wpdb;
		/** @var wpdb $wpdb */

		$items = [];

		$kbe_table       = $wpdb->prefix . 'seojusai_kbe';
		$snapshots_table = $wpdb->prefix . 'seojusai_snapshots';

		$items[] = $this->item(
			'table_kbe',
			$this->table_exists($wpdb, $kbe_table),
			__('Таблиця KBE знайдена', 'seojusai'),
			__('Таблиця KBE відсутня.', 'seojusai')
		);

		$items[] = $this->item(
			'table_snapshots',
			$this->table_exists($wpdb, $snapshots_table),
			__('Таблиця Snapshots знайдена', 'seojusai'),
			__('Таблиця Snapshots відсутня.', 'seojusai')
		);

		return [
			'ok'    => $this->all_ok($items),
			'items' => $items,
		];
	}

	private function check_rest(): array {
		$items = [];

		$items[] = $this->item(
			'rest_enabled',
			function_exists('rest_url') && function_exists('register_rest_route'),
			__('REST API доступний', 'seojusai'),
			__('REST API недоступний.', 'seojusai')
		);

		return [
			'ok'    => $this->all_ok($items),
			'items' => $items,
		];
	}

	private function check_modules(): array {
		$modules = $this->modules->all();
		$items   = [];

		$items[] = $this->item(
			'modules_initialized',
			! empty($modules),
			__('Реєстр модулів ініціалізований', 'seojusai'),
			__('Модулі не ініціалізовані.', 'seojusai')
		);

		$items[] = $this->item(
			'ai_enabled',
			$this->modules->is_enabled('ai'),
			__('AI Core увімкнено', 'seojusai'),
			__('AI Core вимкнено.', 'seojusai')
		);

		return [
			'ok'    => $this->all_ok($items),
			'items' => $items,
			'data'  => [ 'modules' => $modules ],
		];
	}

	private function summarize(array $checks): array {
		$all_ok = true;
		$errors = 0;

		foreach ( $checks as $section ) {
			foreach ( $section['items'] ?? [] as $item ) {
				if ( empty($item['ok']) ) {
					$all_ok = false;
					$errors++;
				}
			}
		}

		$degraded_reasons = [];

		if ( $this->plugin->is_degraded_mode() ) {
			$degraded_reasons[] = __('Degraded Mode активний.', 'seojusai');
		}

		if ( ! $this->modules->is_enabled('ai') ) {
			$degraded_reasons[] = __('AI Core вимкнено.', 'seojusai');
		}

		if ( EmergencyStop::is_active() ) {
			$degraded_reasons[] = __('Аварійна зупинка активна.', 'seojusai');
		}

		$status = EmergencyStop::is_active()
			? 'emergency'
			: ( $all_ok && ! $this->plugin->is_degraded_mode() ? 'ok' : 'degraded' );

		return [
			'status'           => $status,
			'ok'               => $all_ok,
			'errors'           => $errors,
			'degraded_reasons' => $degraded_reasons,
		];
	}

	private function all_ok(array $items): bool {
		foreach ( $items as $i ) {
			if ( empty($i['ok']) ) {
				return false;
			}
		}
		return true;
	}

	private function item(string $key, bool $ok, string $label, string $hint): array {
		return [
			'key'   => $key,
			'ok'    => $ok,
			'label' => $label,
			'hint'  => $ok ? '' : $hint,
		];
	}

	private function table_exists(wpdb $db, string $table): bool {
		$table = preg_replace('/[^a-zA-Z0-9_]/', '', $table);
		if ( ! $table ) {
			return false;
		}
		$found = $db->get_var($db->prepare("SHOW TABLES LIKE %s", $table));
		return (string) $found === $table;
	}
}
