<?php
declare(strict_types=1);

namespace SEOJusAI\Tasks;

use SEOJusAI\AI\SelfTrainingService;

defined('ABSPATH') || exit;

final class SelfTrainModelsTask {

    public function __invoke(array $payload): array {
        $enabled = (bool) get_option('seojusai_self_training_enabled', 0);
        if (!$enabled) {
            return ['ok'=>false,'reason'=>'disabled'];
        }

        $limit = (int) get_option('seojusai_self_training_max_samples', 500);
        if ($limit < 50) $limit = 50;

        $out = [];
        foreach (['page_type','practice_area','search_intent'] as $tax) {
            $out[$tax] = SelfTrainingService::train($tax, $limit);
        }
        return ['ok'=>true,'results'=>$out];
    }
}