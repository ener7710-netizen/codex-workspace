<?php
declare(strict_types=1);

namespace SEOJusAI\Security;

defined('ABSPATH') || exit;

/**
 * SecretsVault
 *
 * Мета:
 * - централізоване зберігання секретів (API keys, service JSON)
 * - зберігання в options, але у ЗАШИФРОВАНОМУ вигляді
 *
 * Обмеження:
 * - це не HSM, але це значно краще за plaintext options
 * - ключ шифрування прив'язаний до wp-config salts
 */
final class SecretsVault {

	private const OPTION_KEY = 'seojusai_secrets_vault_v1';

	/** @return array<string,string> */
	private function load(): array {
		$raw = get_option(self::OPTION_KEY, []);
		return is_array($raw) ? $raw : [];
	}

	/** @param array<string,string> $data */
	private function save(array $data): void {
		update_option(self::OPTION_KEY, $data, false);
	}

	public function has(string $key): bool {
		$key = sanitize_key($key);
		if ($key === '') return false;
		$data = $this->load();
		return !empty($data[$key]);
	}

	public function set(string $key, string $value): bool {
		$key = sanitize_key($key);
		if ($key === '' || $value === '') return false;

		$enc = $this->encrypt($value);
		if ($enc === null) return false;

		$data = $this->load();
		$data[$key] = $enc;
		$this->save($data);
		return true;
	}

	public function get(string $key): string {
		$key = sanitize_key($key);
		if ($key === '') return '';
		$data = $this->load();
		if (empty($data[$key])) return '';
		$dec = $this->decrypt((string)$data[$key]);
		return $dec ?? '';
	}

	public function delete(string $key): void {
		$key = sanitize_key($key);
		$data = $this->load();
		unset($data[$key]);
		$this->save($data);
	}

	private function key_material(): string {
		$k = (string) (defined('AUTH_KEY') ? AUTH_KEY : '');
		$s = (string) (defined('SECURE_AUTH_KEY') ? SECURE_AUTH_KEY : '');
		$h = hash('sha256', $k . '|' . $s . '|' . wp_salt('auth'), true);
		return $h;
	}

	private function encrypt(string $plaintext): ?string {
		$km = $this->key_material();

		// Prefer libsodium if available
		if (function_exists('sodium_crypto_secretbox')) {
			$nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
			$cipher = sodium_crypto_secretbox($plaintext, $nonce, $km);
			return 'sbox:' . base64_encode($nonce . $cipher);
		}

		// Fallback to OpenSSL (AEAD preferred)
		if (function_exists('openssl_encrypt') && function_exists('openssl_decrypt')) {

			// Prefer AES-256-GCM if available (authenticated encryption)
			if (in_array('aes-256-gcm', openssl_get_cipher_methods(true), true)) {
				$iv  = random_bytes(12); // recommended IV size for GCM
				$tag = '';
				$cipher = openssl_encrypt($plaintext, 'aes-256-gcm', $km, OPENSSL_RAW_DATA, $iv, $tag, '', 16);
				if ($cipher === false || !is_string($tag) || strlen($tag) !== 16) return null;
				return 'gcm:' . base64_encode($iv . $tag . $cipher);
			}

			// Last-resort: AES-256-CBC + HMAC-SHA256 (encrypt-then-MAC)
			$iv = random_bytes(16);
			$cipher = openssl_encrypt($plaintext, 'AES-256-CBC', $km, OPENSSL_RAW_DATA, $iv);
			if ($cipher === false) return null;
			$mac = hash_hmac('sha256', $iv . $cipher, $km, true);
			return 'cbc:' . base64_encode($iv . $mac . $cipher);
		}

		return null;
	}

	private function decrypt(string $payload): ?string {
		$km = $this->key_material();

		if (str_starts_with($payload, 'sbox:') && function_exists('sodium_crypto_secretbox_open')) {
			$bin = base64_decode(substr($payload, 5), true);
			if (!is_string($bin) || strlen($bin) < SODIUM_CRYPTO_SECRETBOX_NONCEBYTES) return null;
			$nonce = substr($bin, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
			$cipher = substr($bin, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
			$plain = sodium_crypto_secretbox_open($cipher, $nonce, $km);
			return $plain === false ? null : $plain;
		}

		if (str_starts_with($payload, 'gcm:') && function_exists('openssl_decrypt')) {
			$bin = base64_decode(substr($payload, 4), true);
			if (!is_string($bin) || strlen($bin) < (12 + 16 + 1)) return null;
			$iv  = substr($bin, 0, 12);
			$tag = substr($bin, 12, 16);
			$cipher = substr($bin, 28);
			$plain = openssl_decrypt($cipher, 'aes-256-gcm', $km, OPENSSL_RAW_DATA, $iv, $tag);
			return $plain === false ? null : $plain;
		}

		if (str_starts_with($payload, 'cbc:') && function_exists('openssl_decrypt')) {
			$bin = base64_decode(substr($payload, 4), true);
			if (!is_string($bin) || strlen($bin) < (16 + 32 + 1)) return null;
			$iv  = substr($bin, 0, 16);
			$mac = substr($bin, 16, 32);
			$cipher = substr($bin, 48);

			$calc = hash_hmac('sha256', $iv . $cipher, $km, true);
			if (!hash_equals($mac, $calc)) {
				return null;
			}
			$plain = openssl_decrypt($cipher, 'AES-256-CBC', $km, OPENSSL_RAW_DATA, $iv);
			return $plain === false ? null : $plain;
		}

		// Backward-compat for old payloads
		if (str_starts_with($payload, 'ossl:') && function_exists('openssl_decrypt')) {
			$bin = base64_decode(substr($payload, 5), true);
			if (!is_string($bin) || strlen($bin) < 17) return null;
			$iv = substr($bin, 0, 16);
			$cipher = substr($bin, 16);
			$plain = openssl_decrypt($cipher, 'AES-256-CBC', $km, OPENSSL_RAW_DATA, $iv);
			return $plain === false ? null : $plain;
		}

		return null;
	}
}
