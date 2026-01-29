<?php
declare(strict_types=1);

namespace SEOJusAI\Utils;

use SEOJusAI\Core\EmergencyStop;
use wpdb;

if ( ! defined('ABSPATH') ) {
	exit;
}

/**
 * Logger
 *
 * Централізований логер SEOJusAI.
 * Використовує таблицю {prefix}seojusai_trace для зберігання подій роботи ІІ,
 * помилок API та дій автопілота.
 */
final class Logger {

	/**
	 * Назва таблиці логів (trace).
	 */
	private static ?string $table = null;

	/**
	 * Кеш для перевірки існування таблиці.
	 */
	private static array $table_exists_cache = [];

	/**
	 * Запис інформаційного повідомлення.
	 */
	public static function info(string $module, string $message, array $context = []): void {
		self::write('info', $module, $message, $context);
	}

	/**
	 * Запис попередження.
	 */
	public static function warning(string $module, string $message, array $context = []): void {
		self::write('warning', $module, $message, $context);
	}

	/**
	 * Запис помилки.
	 */
	public static function error(string $module, string $message, array $context = []): void {
		self::write('error', $module, $message, $context);
	}

	/**
	 * Основний метод запису в БД.
	 *
	 * @param string $level   Рівень (info, warning, error).
	 * @param string $module  Назва модуля (напр. 'ai', 'serp', 'autopilot').
	 * @param string $message Текст повідомлення.
	 * @param array  $context Додаткові дані для JSON.
	 */
	private static function write(string $level, string $module, string $message, array $context): void {
		global $wpdb;

		if ( self::$table === null ) {
			self::$table = $wpdb->prefix . 'seojusai_trace';
		}

		// Перевірка існування таблиці (з кешуванням у межах одного запиту)
		if ( ! self::check_table() ) {
			return;
		}

		// Санітизація даних
		$data = [
			'level'      => sanitize_key($level),
			'module'     => sanitize_text_field($module),
			'message'    => wp_strip_all_tags($message),
			'context'    => (string) wp_json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
			'created_at' => current_time('mysql'),
		];

		// Використовуємо wpdb->insert для безпечного запису
		$wpdb->insert(
			self::$table,
			$data,
			[ '%s', '%s', '%s', '%s', '%s' ]
		);
	}

	/**
	 * Перевірка наявності таблиці в БД.
	 */
	private static function check_table(): bool {
		global $wpdb;

		if ( isset(self::$table_exists_cache[self::$table]) ) {
			return self::$table_exists_cache[self::$table];
		}

		$found = $wpdb->get_var(
			$wpdb->prepare("SHOW TABLES LIKE %s", self::$table)
		);

		self::$table_exists_cache[self::$table] = ( (string) $found === self::$table );

		return self::$table_exists_cache[self::$table];
	}

	/**
	 * Очищення старих логів (наприклад, старше 30 днів).
	 * Може викликатися через WP-Cron.
	 */
	public static function rotate(int $days = 30): void {
		global $wpdb;

		if ( ! self::check_table() ) {
			return;
		}

		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM " . self::$table . " WHERE created_at < DATE_SUB(NOW(), INTERVAL %d DAY)",
				$days
			)
		);
	}
}
