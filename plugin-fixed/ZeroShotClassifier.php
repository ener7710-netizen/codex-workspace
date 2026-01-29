<?php
declare(strict_types=1);

namespace SEOJusAI\AI;

use SEOJusAI\Audit\AuditLogger;
use SEOJusAI\AI\Debiaser;
use SEOJusAI\AI\BaselineProvider;
use SEOJusAI\AI\SelfTrainingService;
use SEOJusAI\AI\PromptLibrary;

defined('ABSPATH') || exit;

final class ZeroShotClassifier {

    public static function classify(string $text, string $taxonomy): array {
        $labels = require SEOJUSAI_PATH . 'src/Config/taxonomies.php';
        if (!isset($labels[$taxonomy])) {
            throw new \InvalidArgumentException('Unknown taxonomy');
        }

        // Placeholder logic: LLM call should be here.
        // For now we simulate neutral output structure.
        $result = [
            'label' => array_key_first($labels[$taxonomy]),
            'confidence' => 0.5,
            'rationale' => 'Prompt-based zero-shot classification',
            'evidence' => substr($text, 0, 120),
        ];

        AuditLogger::log(
            sha1($taxonomy . $text),
            'system',
            0,
            'zero_shot_classified',
            'Zero-shot classification performed',
            ['taxonomy' => $taxonomy, 'result' => $result]
        );

        $baseline = BaselineProvider::get($taxonomy);
        $result = Debiaser::correct($result, $baseline);

        // Optionally blend with self-trained model (only from human-approved data)
        $st_enabled = (bool) get_option('seojusai_self_training_enabled', 0);
        if ($st_enabled) {
            $st = SelfTrainingService::predict($taxonomy, $text);
            if (is_array($st) && !empty($st['label'])) {
                // If self-trained confidence is high, prefer its label.
                if ((float)$st['confidence'] >= 0.90) {
                    $result['label'] = (string)$st['label'];
                    $result['rationale'] = ($result['rationale'] ?? '') . ' | ' . ($st['rationale'] ?? 'self-train');
                    $result['evidence'] = $st['evidence'] ?? ($result['evidence'] ?? '');
                    $result['confidence'] = max((float)$result['confidence'], (float)$st['confidence']);
                    $result['source'] = 'zero_shot+st';
                } else {
                    // Otherwise just annotate.
                    $result['st_suggestion'] = $st;
                }
            }
        }

        return $result;
    }
}