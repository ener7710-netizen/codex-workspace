<?php
declare(strict_types=1);

namespace SEOJusAI\Rest\Controllers;

use SEOJusAI\Rest\RestKernel;
use SEOJusAI\Rest\Contracts\RestControllerInterface;
use SEOJusAI\AIMonitoring\Conversion\ConversionRepository;
use SEOJusAI\Input\Input;

defined('ABSPATH') || exit;

final class ConversionController implements RestControllerInterface {

    private const NS = 'seojusai/v1';

    public function register_routes(): void {
        register_rest_route(self::NS, '/conversion', [
            'methods' => 'POST',
            'callback' => [$this, 'track'],
            'permission_callback' => [ self::class, 'can_track' ],
            'args' => [],
        ]);
    }


    public static function can_track(\WP_REST_Request $req): bool {
        // Allow admin-side tracking via normal REST gate
        if (RestKernel::can_execute($req)) {
            return true;
        }

        // Public tracking requires a dedicated nonce (generated in ConversionTracker)
        $nonce = (string) $req->get_header('X-WP-Nonce');
        if ($nonce === '' && isset($_SERVER['HTTP_X_WP_NONCE'])) {
            $nonce = (string) $_SERVER['HTTP_X_WP_NONCE'];
        }
        if ($nonce === '') {
            $nonce = (string) ($req->get_param('nonce') ?? '');
        }

        return $nonce !== '' && (bool) wp_verify_nonce($nonce, 'seojusai_conversion');
    }

public function track(\WP_REST_Request $req): \WP_REST_Response {
        $nonce = (string) $req->get_header('x-seojusai-nonce');
        if (!$nonce) {
            $nonce = (string) ($req->get_param('nonce') ?? '');
        }
        if (!wp_verify_nonce($nonce, 'seojusai_conversion')) {
            return new \WP_REST_Response(['ok' => false], 403);
        }

                $event_type = Input::string($req->get_param('event_type') ?? 'unknown', 32, true);
        $source = Input::string($req->get_param('source') ?? 'unknown', 32, true);
$post_id = Input::int($req->get_param('post_id') ?? 0, 0, 2147483647);
        $session_id = sanitize_text_field((string) ($req->get_param('session_id') ?? ''));

        if ($event_type === '') $event_type = 'unknown';
        if ($source === '') $source = 'unknown';

        // normalize AI hints
        $ref = (string) ($req->get_param('ref') ?? '');
        if ($source === 'unknown' && $ref) {
            $s = strtolower($ref);
            if (strpos($s, 'chatgpt') !== false || strpos($s, 'openai') !== false) $source = 'openai';
            elseif (strpos($s, 'gemini') !== false || strpos($s, 'bard') !== false) $source = 'gemini';
            elseif (strpos($s, 'claude') !== false || strpos($s, 'anthropic') !== false) $source = 'claude';
            elseif (strpos($s, 'perplexity') !== false) $source = 'perplexity';
            elseif (strpos($s, 'copilot') !== false || strpos($s, 'bing') !== false) $source = 'copilot';
        }

        $meta = $req->get_param('meta');
        $meta_arr = is_array($meta) ? $meta : [];
        // allow only small whitelisted meta keys
        $lead_kind = isset($meta_arr['lead_kind']) ? sanitize_key((string)$meta_arr['lead_kind']) : '';
        $dwell_s = isset($meta_arr['dwell_s']) ? (int)$meta_arr['dwell_s'] : 0;

        $stored_meta = [
            'lead_kind' => $lead_kind,
            'dwell_s' => $dwell_s,
            'ua' => substr((string) $req->get_header('user-agent'), 0, 255),
        ];

        $repo = new ConversionRepository();
        $repo->insert([
            'ts' => time(),
            'post_id' => $post_id > 0 ? $post_id : 0,
            'source' => $source,
            'event_type' => $event_type,
            'session_id' => $session_id,
            'meta' => $stored_meta,
        ]);

        return new \WP_REST_Response(['ok' => true], 200);
    }
}
