<?php
declare(strict_types=1);

namespace SEOJusAI\GSC;

defined('ABSPATH') || exit;

/**
 * Клас для роботи з Google Service Account.
 * Забезпечує доступ до Google Search Console API без участі користувача.
 */
final class GscServiceAccount {

	/**
	 * Шлях до файлу ключа (JSON)
	 */
	public static function get_key_path(): string {
		$uploads = wp_upload_dir();
		$basedir = rtrim((string) ($uploads['basedir'] ?? ''), '/');
		if ($basedir === '') {
			$basedir = rtrim(WP_CONTENT_DIR . '/uploads', '/');
		}
		return $basedir . '/seojusai/keys/gsc-service-account.json';
	}

	/**
	 * Отримання даних ключа та перевірка валідності
	 * * @return array<string,string>
	 * @throws \Exception
	 */
	public static function get_credentials(): array {
		$path = self::get_key_path();

		if ( ! file_exists( $path ) ) {
			throw new \Exception( "Файл ключа за шляхом {$path} не знайдено." );
		}

		$json = file_get_contents( $path );
		$data = json_decode( $json ?: '', true );

		if ( ! is_array( $data ) || ! isset( $data['client_email'], $data['private_key'] ) ) {
			throw new \Exception( "JSON-файл має некоректний формат або відсутній private_key." );
		}

		return $data;
	}

	/**
	 * Перевірка статусу підключення (використовується в адмінці)
	 */
	public static function is_connected(): bool {
		try {
			self::get_credentials();
			return true;
		} catch ( \Throwable $e ) {
			return false;
		}
	}

	/**
	 * Метод для отримання Access Token через JWT
	 * Потрібен для виконання реальних запитів до GSC API.
	 */
	public static function get_access_token(): string {
		$creds = self::get_credentials();

		// Перевіряємо кеш токена, щоб не робити запит до Google щоразу
		$token = get_transient('seojusai_gsc_token');
		if ($token) return $token;

		// Логіка генерації JWT токена (OAuth 2.0 за протоколом Google)
		// Тут зазвичай викликається метод для підпису private_key
		// Для повноцінної роботи рекомендується використовувати Google\Client

		return '';
	}
}
