<?php
declare(strict_types=1);

namespace SEOJusAI\Crawl;

defined('ABSPATH') || exit;

/**
 * HtmlSnapshot
 * Отримує фінальний рендер сторінки (те, що бачить Google Bot).
 */
final class HtmlSnapshot {

    private const META_KEY_URL  = '_seojusai_snapshot_url';
    private const META_KEY_TS   = '_seojusai_snapshot_ts';
    private const META_KEY_PATH = '_seojusai_snapshot_path';

    /**
     * Load stored snapshot for a post.
     *
     * Snapshot is stored as gzipped HTML file in uploads/seojusai/snapshots.
     */
    public static function load_for_post(int $post_id): ?HtmlSnapshotValue {
        if ($post_id <= 0) {
            return null;
        }

        $path = (string) get_post_meta($post_id, self::META_KEY_PATH, true);
        $url  = (string) get_post_meta($post_id, self::META_KEY_URL, true);
        $ts   = (int) get_post_meta($post_id, self::META_KEY_TS, true);

        if ($path === '' || !file_exists($path)) {
            return null;
        }

        $html = self::read_gz_file($path);
        if ($html === '') {
            return null;
        }

        return new HtmlSnapshotValue($url !== '' ? $url : (string) get_permalink($post_id), $html, $ts > 0 ? $ts : time());
    }

    /**
     * Capture and store a fresh snapshot for a post.
     */
    public static function refresh_for_post(int $post_id, bool $force = false): ?HtmlSnapshotValue {
        if ($post_id <= 0) {
            return null;
        }

        $url = (string) get_permalink($post_id);
        if ($url === '') {
            return null;
        }

        // simple TTL to avoid hammering in admin lists
        $ts = (int) get_post_meta($post_id, self::META_KEY_TS, true);
        if (!$force && $ts > 0 && (time() - $ts) < 6 * HOUR_IN_SECONDS) {
            return self::load_for_post($post_id);
        }

        $snap = self::capture($url);
        if (!empty($snap['error']) || empty($snap['html'])) {
            return null;
        }

        $stored = self::store_snapshot($post_id, $url, (string) $snap['html'], (int) ($snap['captured_at'] ?? time()));
        return $stored;
    }

    private static function store_snapshot(int $post_id, string $url, string $html, int $ts): ?HtmlSnapshotValue {
        $dir = self::ensure_dir();
        if ($dir === '') {
            return null;
        }

        $file = trailingslashit($dir) . 'post-' . $post_id . '.html.gz';
        if (!self::write_gz_file($file, $html)) {
            return null;
        }

        update_post_meta($post_id, self::META_KEY_URL, esc_url_raw($url));
        update_post_meta($post_id, self::META_KEY_TS, $ts);
        update_post_meta($post_id, self::META_KEY_PATH, $file);

        return new HtmlSnapshotValue($url, $html, $ts);
    }

    private static function ensure_dir(): string {
        $upload = wp_upload_dir();
        $base = isset($upload['basedir']) ? (string) $upload['basedir'] : '';
        if ($base === '') {
            return '';
        }
        $dir = trailingslashit($base) . 'seojusai/snapshots';
        if (!is_dir($dir)) {
            wp_mkdir_p($dir);
        }
        return is_dir($dir) ? $dir : '';
    }

    private static function write_gz_file(string $file, string $html): bool {
        $gz = @gzopen($file, 'wb9');
        if (!$gz) {
            return false;
        }
        @gzwrite($gz, $html);
        @gzclose($gz);
        return file_exists($file) && filesize($file) > 0;
    }

    private static function read_gz_file(string $file): string {
        $gz = @gzopen($file, 'rb');
        if (!$gz) {
            return '';
        }
        $out = '';
        while (!gzeof($gz)) {
            $out .= (string) gzread($gz, 8192);
            if (strlen($out) > 2_000_000) { // hard cap 2MB
                break;
            }
        }
        @gzclose($gz);
        return $out;
    }

    public static function capture(string $url): array {

        $url = esc_url_raw($url);

		// SSRF hardening: allow only same-host requests.
		$homeHost = (string) parse_url((string) home_url('/'), PHP_URL_HOST);
		$reqHost  = (string) parse_url($url, PHP_URL_HOST);
		if ($homeHost !== '' && $reqHost !== '' && strcasecmp($homeHost, $reqHost) !== 0) {
			return [
				'url'         => $url,
				'http_code'   => 0,
				'html'        => '',
				'size'        => 0,
				'captured_at' => time(),
				'error'       => 'External URL not allowed',
			];
		}

        if ($url === '') {
            return [
                'url'         => '',
                'http_code'   => 0,
                'html'        => '',
                'size'        => 0,
                'captured_at' => time(),
                'error'       => 'Empty URL',
            ];
        }

		$response = wp_safe_remote_get($url, [
			'timeout'            => 25,
			'redirection'        => 3,
			'sslverify'          => true,
			'reject_unsafe_urls' => true,
            'user-agent'  => 'Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)', // Імітуємо Googlebot
            'headers'     => [
                'Accept'          => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                'Accept-Language' => 'uk-UA,uk;q=0.9,en-US;q=0.8,en;q=0.7',
            ],
        ]);

        if (is_wp_error($response)) {
            return [
                'url'         => $url,
                'http_code'   => 0,
                'html'        => '',
                'size'        => 0,
                'captured_at' => time(),
                'error'       => 'WP_Error: ' . $response->get_error_message(),
            ];
        }

        $code = (int) wp_remote_retrieve_response_code($response);
        $html = (string) wp_remote_retrieve_body($response);

        // Перевірка на "тихе" блокування (код 200, але тіло порожнє або замале)
        if ($code !== 200 || strlen(trim($html)) < 200) {
            return [
                'url'         => $url,
                'http_code'   => $code,
                'html'        => '',
                'size'        => strlen($html),
                'captured_at' => time(),
                'error'       => "Invalid response: Code $code, Body size: " . strlen($html),
            ];
        }

        return [
            'url'         => $url,
            'http_code'   => $code,
            'html'        => $html,
            'size'        => strlen($html),
            'captured_at' => time(),
        ];
    }
}
