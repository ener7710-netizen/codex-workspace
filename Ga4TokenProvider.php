<?php
declare(strict_types=1);

namespace SEOJusAI\GA4;

defined('ABSPATH') || exit;

/**
 * Ga4TokenProvider
 *
 * Отримує OAuth2 access_token для Google APIs через Service Account (JWT, RS256).
 * Scope: analytics.readonly
 */
final class Ga4TokenProvider {

    private const TOKEN_URL = 'https://oauth2.googleapis.com/token';
    private const SCOPE     = 'https://www.googleapis.com/auth/analytics.readonly';
    private const CACHE_KEY = 'seojusai_ga4_service_token';

    /**
     * @throws \RuntimeException
     */
    public static function get_access_token(): string {

        // 1) cache
        $cached = get_transient(self::CACHE_KEY);
        if (is_array($cached) && !empty($cached['token']) && !empty($cached['exp'])) {
            if ((int) $cached['exp'] > time() + 60) {
                return (string) $cached['token'];
            }
        }

        // 2) creds
        $creds = Ga4ServiceAccount::get_credentials();
        $now   = time();

        $jwt_header = [
            'alg' => 'RS256',
            'typ' => 'JWT',
        ];

        $jwt_claims = [
            'iss'   => (string) $creds['client_email'],
            'scope' => self::SCOPE,
            'aud'   => self::TOKEN_URL,
            'iat'   => $now,
            'exp'   => $now + 3600,
        ];

        $jwt = self::encode_jwt($jwt_header, $jwt_claims, (string) $creds['private_key']);

        // 3) token request
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
            throw new \RuntimeException('GA4 token request failed');
        }

        $data = json_decode((string) wp_remote_retrieve_body($response), true);
        if (!is_array($data) || empty($data['access_token'])) {
            throw new \RuntimeException('Invalid GA4 token response');
        }

        $expires_in = (int) ($data['expires_in'] ?? 3600);

        set_transient(
            self::CACHE_KEY,
            [
                'token' => (string) $data['access_token'],
                'exp'   => time() + $expires_in,
            ],
            max(60, $expires_in - 60)
        );

        return (string) $data['access_token'];
    }

    /* ================= JWT helpers ================= */

    /**
     * @param array<string,mixed> $header
     * @param array<string,mixed> $claims
     */
    private static function encode_jwt(array $header, array $claims, string $private_key): string {

        $base64url = static function ($data): string {
            return rtrim(strtr(base64_encode(json_encode($data)), '+/', '-_'), '=');
        };

        $segments = [];
        $segments[] = $base64url($header);
        $segments[] = $base64url($claims);

        $signing_input = implode('.', $segments);

        $signature = '';
        $ok = openssl_sign($signing_input, $signature, $private_key, 'RSA-SHA256');
        if (!$ok) {
            throw new \RuntimeException('GA4 JWT signing failed');
        }

        $segments[] = rtrim(strtr(base64_encode($signature), '+/', '-_'), '=');

        return implode('.', $segments);
    }
}
