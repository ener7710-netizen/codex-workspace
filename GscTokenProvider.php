<?php
declare(strict_types=1);

namespace SEOJusAI\GSC;

defined('ABSPATH') || exit;

/**
 * GscTokenProvider
 *
 * Отримує access_token для Google API
 * через Service Account (JWT, RS256)
 *
 * Використовується backend / AI / Gemini
 * ❗ БЕЗ UI, БЕЗ OAuth screen
 */
final class GscTokenProvider {

	private const TOKEN_URL = 'https://oauth2.googleapis.com/token';
	private const SCOPE     = 'https://www.googleapis.com/auth/webmasters.readonly';
	private const CACHE_KEY = 'seojusai_gsc_service_token';

	/**
	 * @throws \RuntimeException
	 */
	public static function get_access_token(): string {

		// 1️⃣ пробуем из кеша
		$cached = get_transient(self::CACHE_KEY);
		if (is_array($cached) && !empty($cached['token']) && !empty($cached['exp'])) {
			if ($cached['exp'] > time() + 60) {
				return $cached['token'];
			}
		}

		// 2️⃣ берем credentials
		$creds = GscServiceAccount::get_credentials();

		$now = time();

		$jwt_header = [
			'alg' => 'RS256',
			'typ' => 'JWT',
		];

		$jwt_claims = [
			'iss'   => $creds['client_email'],
			'scope' => self::SCOPE,
			'aud'   => self::TOKEN_URL,
			'iat'   => $now,
			'exp'   => $now + 3600,
		];

		$jwt = self::encode_jwt(
			$jwt_header,
			$jwt_claims,
			$creds['private_key']
		);

		// 3️⃣ запрашиваем access_token
		$response = wp_remote_post(self::TOKEN_URL, [
			'timeout' => 20,
			'headers' => [
				'Content-Type' => 'application/x-www-form-urlencoded',
			],
			'body' => [
				'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
				'assertion'  => $jwt,
			],
		]);

		if (is_wp_error($response)) {
			throw new \RuntimeException('GSC token request failed');
		}

		$data = json_decode((string) wp_remote_retrieve_body($response), true);

		if (!is_array($data) || empty($data['access_token'])) {
			throw new \RuntimeException('Invalid GSC token response');
		}

		$expires_in = (int) ($data['expires_in'] ?? 3600);

		// 4️⃣ кешируем
		set_transient(
			self::CACHE_KEY,
			[
				'token' => $data['access_token'],
				'exp'   => time() + $expires_in,
			],
			$expires_in - 60
		);

		return $data['access_token'];
	}

	/* ================= JWT ================= */

	private static function encode_jwt(array $header, array $claims, string $private_key): string {

		$base64url = static function ($data): string {
			return rtrim(strtr(base64_encode(json_encode($data)), '+/', '-_'), '=');
		};

		$segments = [];
		$segments[] = $base64url($header);
		$segments[] = $base64url($claims);

		$signing_input = implode('.', $segments);

		$signature = '';
		$success = openssl_sign(
			$signing_input,
			$signature,
			$private_key,
			'RSA-SHA256'
		);

		if (!$success) {
			throw new \RuntimeException('JWT signing failed');
		}

		$segments[] = rtrim(strtr(base64_encode($signature), '+/', '-_'), '=');

		return implode('.', $segments);
	}
}
