<?php
declare(strict_types=1);

namespace SEOJusAI\GA4;

defined('ABSPATH') || exit;

/**
 * Ga4ServiceAccount
 *
 * Завантажує credentials Service Account для Google Analytics Data API (GA4).
 * Зберігання — аналогічно GSC:
 *   wp-content/uploads/seojusai/keys/gsc-service-account.json
 */
final class Ga4ServiceAccount {

    public static function get_key_path(): string {
		// ЄДИНИЙ КЛЮЧ ДЛЯ GA4 і GSC: використовуємо той самий файл, що і для GSC.
		$uploads = wp_upload_dir();
		$basedir = rtrim((string) ($uploads['basedir'] ?? ''), '/');
		if ($basedir === '') {
			$basedir = rtrim(WP_CONTENT_DIR . '/uploads', '/');
		}
		return $basedir . '/seojusai/keys/gsc-service-account.json';
	}

    /**
     * @return array<string,mixed>
     * @throws \Exception
     */
    public static function get_credentials(): array {
        $path = self::get_key_path();
        if (!file_exists($path)) {
            throw new \Exception("Файл ключа за шляхом {$path} не знайдено. (Підказка: можна використати один ключ як для GSC, так і для GA4 — достатньо зберегти його як gsc-service-account.json)");
        }

        $json = file_get_contents($path);
        $data = json_decode($json ?: '', true);

        if (!is_array($data) || empty($data['client_email']) || empty($data['private_key'])) {
            throw new \Exception('JSON-файл має некоректний формат або відсутній private_key/client_email.');
        }

        return $data;
    }

    public static function is_connected(): bool {
        try {
            self::get_credentials();
            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }
}
