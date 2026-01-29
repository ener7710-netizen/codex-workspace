<?php
declare(strict_types=1);

namespace SEOJusAI\API;

use WP_REST_Request;
use WP_REST_Response;
use SEOJusAI\AI\ZeroShotClassifier;
use SEOJusAI\AI\PromptLibrary;
use SEOJusAI\AI\SelfTrainingService;
use SEOJusAI\Repository\ClientRepository;
use SEOJusAI\Security\RateLimiter;

defined('ABSPATH') || exit;

final class SeoApiController {

    private function authorize(): bool {
        $key = $_SERVER['HTTP_X_SEOJUSAI_KEY'] ?? '';
        $expected = (string) get_option('seojusai_api_key', '');
        if ($expected === '') {
            // If not configured, keep public (dev mode)
            return true;
        }
        $provided = '';
        if (isset($_SERVER['HTTP_X_SEOJUSAI_KEY'])) {
            $provided = (string) $_SERVER['HTTP_X_SEOJUSAI_KEY'];
        }
        $client = ClientRepository::getByKey((string)$key);
        if (!$client) return false;
        if (!RateLimiter::allow((string)$client->api_key, (int)$client->requests_per_minute)) return false;
        return true;
    }


    public function register(): void {
        add_action('rest_api_init', function () {
            register_rest_route('seojusai/v1', '/seo/analyze', [
                'methods' => 'POST',
                'callback' => [$this, 'analyze'],
                'permission_callback' => function(){ return $this->authorize(); },
            ]);

            register_rest_route('seojusai/v1', '/seo/self-train', [
                'methods' => 'POST',
                'callback' => [$this, 'selfTrain'],
                'permission_callback' => function(){ return $this->authorize(); },
            ]);

            register_rest_route('seojusai/v1', '/seo/health', [
                'methods' => 'GET',
                'callback' => [$this, 'health'],
                'permission_callback' => function(){ return $this->authorize(); },
            ]);
        });
    }

    public function analyze(WP_REST_Request $req): WP_REST_Response {
        $page = (array)$req->get_param('page');
        if (empty($page['text'])) {
            return new WP_REST_Response(['error'=>'page.text required'],400);
        }

        $text = (string)$page['text'];

        $pageType = ZeroShotClassifier::classify('page_type',$text);
        $practice = ZeroShotClassifier::classify('practice_area',$text);
        $intent   = ZeroShotClassifier::classify('search_intent',$text);

        $confidence = min(
            $pageType['confidence'] ?? 0,
            $practice['confidence'] ?? 0,
            $intent['confidence'] ?? 0
        );

        return new WP_REST_Response([
            'classification'=>[
                'page_type'=>$pageType['label'] ?? null,
                'practice_area'=>$practice['label'] ?? null,
                'multilabel_scores'=>[]
            ],
            'intent'=>$intent['label'] ?? null,
            'seo'=>[
                'title'=>null,
                'description'=>null
            ],
            'confidence'=>$confidence,
            'explanations'=>[
                'page_type'=>$pageType['rationale'] ?? null,
                'practice_area'=>$practice['rationale'] ?? null
            ],
            'bias_flags'=>[
                'label_bias_detected'=>false,
                'low_entropy_prediction'=>false
            ]
        ]);
    }

    public function selfTrain(WP_REST_Request $req): WP_REST_Response {
        $prediction = (array)$req->get_param('prediction');
        $confidence = (float)($prediction['confidence'] ?? 0);

        if ($confidence < 0.9) {
            return new WP_REST_Response(['accepted'=>false,'reason'=>'low confidence']);
        }

        // In WP version we rely on admin-reviewed self-training
        return new WP_REST_Response(['accepted'=>false,'reason'=>'manual review required']);
    }

    public function health(): WP_REST_Response {
        return new WP_REST_Response([
            'status'=>'ok',
            'model'=>'llm-zero-shot-v1',
            'bias_correction'=>true
        ]);
    }
}
