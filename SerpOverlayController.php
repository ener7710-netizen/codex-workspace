<?php
declare(strict_types=1);

namespace SEOJusAI\Rest\Controllers;

defined('ABSPATH') || exit;

use WP_REST_Request;
use WP_REST_Response;
use SEOJusAI\Rest\RestKernel;
use SEOJusAI\Rest\AbstractRestController;
use SEOJusAI\Rest\Contracts\RestControllerInterface;
use SEOJusAI\SERP\SerpOverlayService;
use SEOJusAI\AI\Integrations\GeminiSerpOverlayGateway;

/**
 * SerpOverlayController
 *
 * Read-only endpoint для SERP competitor overlay.
 *
 * Повертає:
 * - SERP факти (через SerpAPI)
 * - опційно: короткий JSON-висновок Gemini (на фактах SERP)
 */
final class SerpOverlayController extends AbstractRestController implements RestControllerInterface {

    public function register_routes(): void {

        register_rest_route('seojusai/v1', '/analytics/serp-overlay', [
            'methods'             => 'GET',
            'permission_callback' => [ RestKernel::class, 'can_manage' ],
            'callback'            => [ $this, 'overlay' ],
        ]);
    }

    public function overlay(WP_REST_Request $request): WP_REST_Response {

        $keyword = sanitize_text_field((string) $request->get_param('keyword'));
        $hl      = sanitize_key((string) $request->get_param('hl'));
        $gl      = sanitize_key((string) $request->get_param('gl'));
        $device  = sanitize_key((string) $request->get_param('device'));
        $limit   = (int) $request->get_param('limit');
        $ai      = (string) $request->get_param('ai');

        if ($hl === '') { $hl = 'uk'; }
        if ($gl === '') { $gl = 'ua'; }
        if ($limit <= 0) { $limit = 10; }

        $svc = new SerpOverlayService();
        $serp = $svc->get_overlay($keyword, [
            'hl' => $hl,
            'gl' => $gl,
            'device' => $device,
            'limit' => $limit,
        ]);

        if (!($serp['ok'] ?? false)) {
            $code = (string) ($serp['error'] ?? 'serp_failed');
            $msg = __('Не вдалося отримати SERP overlay. Перевір SerpAPI ключ.', 'seojusai');
            return $this->ok([
                'ok' => false,
                'code' => $code,
                'message' => $msg,
            ]);
        }

        $payload = [
            'ok' => true,
            'keyword' => (string) ($serp['keyword'] ?? $keyword),
            'serp' => [
                'snapshot_id' => (int) ($serp['snapshot_id'] ?? 0),
                'fetched_at'  => (int) ($serp['fetched_at'] ?? 0),
                'results'     => (array) ($serp['results'] ?? []),
                'params'      => (array) ($serp['params'] ?? []),
            ],
        ];

        $include_ai = !in_array(strtolower($ai), ['0','false','no'], true);
        if ($include_ai && $payload['serp']['snapshot_id'] > 0) {
            $ai_out = GeminiSerpOverlayGateway::analyze([
                'keyword' => $payload['keyword'],
                'serp' => [
                    'snapshot_id' => $payload['serp']['snapshot_id'],
                    'fetched_at' => $payload['serp']['fetched_at'],
                    'results' => $payload['serp']['results'],
                ],
            ]);
            if (is_array($ai_out)) {
                $payload['gemini'] = $ai_out;
            }
        }

        return $this->ok($payload);
    }
}
