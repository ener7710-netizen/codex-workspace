<?php
declare(strict_types=1);

namespace SEOJusAI\AI\Engine;

use WP_REST_Request;
use WP_REST_Response;
use SEOJusAI\AI\Strategy\LegalAIStrategy;

defined('ABSPATH') || exit;

if (!class_exists(LegalAIStrategy::class)) {
    $p = dirname(__DIR__) . '/Strategy/LegalAIStrategy.php';
    if (file_exists($p)) {
        require_once $p;
    }
}

trait RestTrait {

    public static function register_rest_routes(): void {

        add_action('rest_api_init', function () {

            register_rest_route('seojusai/v1', '/analyze', [
                'methods'             => 'POST',
                'permission_callback' => [self::class, 'can_manage'],
                'callback'            => [self::class, 'rest_analyze_post'],
            ]);

            register_rest_route('seojusai/v1', '/analysis/(?P<post_id>\d+)', [
                'methods'             => 'GET',
                'permission_callback' => [self::class, 'can_manage'],
                'callback'            => [self::class, 'rest_get_analysis'],
            ]);

            register_rest_route('seojusai/v1', '/analysis/(?P<post_id>\d+)/summary', [
                'methods'             => 'GET',
                'permission_callback' => [self::class, 'can_manage'],
                'callback'            => [self::class, 'rest_get_summary'],
            ]);
        });
    }

    public static function can_manage(): bool {
        return current_user_can('manage_options');
    }

    public static function rest_analyze_post(WP_REST_Request $request): WP_REST_Response {

        $post_id = (int) $request->get_param('post_id');

        if ($post_id <= 0) {
            return new WP_REST_Response([
                'ok'      => false,
                'message' => 'Invalid post_id',
            ], 400);
        }

        if (!method_exists(static::class, 'analyze_post')) {
            return new WP_REST_Response([
                'ok'      => false,
                'message' => 'Engine analyze_post() not found',
            ], 500);
        }

        try {
            $result = static::analyze_post($post_id);
        } catch (\Throwable $e) {
            return new WP_REST_Response([
                'ok'      => false,
                'message' => 'Engine exception',
                'error'   => $e->getMessage(),
            ], 500);
        }

        if (
            is_array($result)
            && !empty($result['facts'])
            && class_exists(LegalAIStrategy::class)
        ) {
            try {
                $ai = LegalAIStrategy::run(
                    (array) $result['facts'],
                    [
                        'score'    => $result['score'] ?? 0,
                        'analysis' => $result['analysis'] ?? [],
                    ]
                );

                if (!empty($ai['ai_tasks'])) {
                    $result['tasks'] = array_merge(
                        $result['tasks'] ?? [],
                        $ai['ai_tasks']
                    );
                }

                if (!empty($ai['ai_explain'])) {
                    $result['ai_explain'] = $ai['ai_explain'];
                }

                if (!empty($ai['ai_schema'])) {
                    $result['ai_schema'] = $ai['ai_schema'];
                }

                $result['mode'] = 'ai_assisted';

                $data = get_post_meta($post_id, '_seojusai_analysis_data', true);
                if (is_array($data)) {
                    $data['tasks'] = $result['tasks'] ?? ($data['tasks'] ?? []);
                    $data['mode']  = 'ai_assisted';
                    if (!empty($result['ai_explain'])) {
                        $data['ai_explain'] = $result['ai_explain'];
                    }
                    if (!empty($result['ai_schema'])) {
                        $data['ai_schema'] = $result['ai_schema'];
                    }
                    update_post_meta($post_id, '_seojusai_analysis_data', $data);
                }

            } catch (\Throwable $e) {
            }
        }

        return new WP_REST_Response($result, 200);
    }

    public static function rest_get_analysis(WP_REST_Request $request): WP_REST_Response {

        $post_id = (int) $request->get_param('post_id');

        if ($post_id <= 0) {
            return new WP_REST_Response([
                'ok'      => false,
                'message' => 'Invalid post_id',
            ], 400);
        }

        $data = get_post_meta($post_id, '_seojusai_analysis_data', true);

        if (!is_array($data) || empty($data)) {
            return new WP_REST_Response([
                'ok'      => false,
                'message' => 'Analysis not found',
            ], 404);
        }

        return new WP_REST_Response([
            'ok'        => true,
            'post_id'   => $post_id,
            'score'     => (int) ($data['score'] ?? 0),
            'analysis'  => (array) ($data['analysis'] ?? []),
            'tasks'     => (array) ($data['tasks'] ?? []),
            'facts'     => (array) ($data['facts'] ?? []),
            'updated'   => (string) ($data['updated_at'] ?? ''),
            'mode'      => (string) ($data['mode'] ?? 'no_ai'),
            'ai_explain'=> (array) ($data['ai_explain'] ?? []),
            'ai_schema' => (array) ($data['ai_schema'] ?? []),
        ], 200);
    }

    public static function rest_get_summary(WP_REST_Request $request): WP_REST_Response {

        $post_id = (int) $request->get_param('post_id');

        if ($post_id <= 0) {
            return new WP_REST_Response([
                'ok'      => false,
                'message' => 'Invalid post_id',
            ], 400);
        }

        $data = get_post_meta($post_id, '_seojusai_analysis_data', true);

        if (!is_array($data) || empty($data)) {
            return new WP_REST_Response([
                'ok'      => false,
                'message' => 'Analysis not found',
            ], 404);
        }

        $analysis = (array) ($data['analysis'] ?? []);

        return new WP_REST_Response([
            'ok'       => true,
            'post_id'  => $post_id,
            'score'    => (int) ($data['score'] ?? 0),
            'summary'  => self::build_summary($analysis),
            'updated'  => (string) ($data['updated_at'] ?? ''),
            'mode'     => (string) ($data['mode'] ?? 'no_ai'),
        ], 200);
    }

    protected static function build_summary(array $analysis): array {

        $out = [
            'good'    => 0,
            'warning' => 0,
            'bad'     => 0,
        ];

        foreach ($analysis as $row) {
            $status = $row['status'] ?? '';
            if (isset($out[$status])) {
                $out[$status]++;
            }
        }

        return $out;
    }
}
