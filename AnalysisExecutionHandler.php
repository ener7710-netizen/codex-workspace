<?php
declare(strict_types=1);

namespace SEOJusAI\Execution\Handlers;

defined('ABSPATH') || exit;

use SEOJusAI\Execution\DTO\ExecutionIntentDTO;
use SEOJusAI\Execution\DTO\ExecutionResultDTO;
use SEOJusAI\Execution\AnalysisResultRepository;

/**
 * AnalysisExecutionHandler
 *
 * Handles intent_type = ANALYSIS.
 *
 * @boundary Execution is controlled exclusively by AutopilotExecutionLoop.
 * UI, REST, and Admin layers must never call this handler.
 * @boundary READ-ONLY execution: must not modify posts, meta, options, or structure.
 */
final class AnalysisExecutionHandler
{
    private AnalysisResultRepository $results;

    public function __construct(?AnalysisResultRepository $results = null)
    {
        $this->results = $results ?: new AnalysisResultRepository();
    }

    public function handle(ExecutionIntentDTO $intent): ExecutionResultDTO
    {
        $payload = $intent->payload();

        $postId = isset($payload['post_id']) ? (int) $payload['post_id'] : 0;

        // Safe, read-only analysis (minimal baseline).
        $analysis = [
            'intent_id' => $intent->id(),
            'post_id'   => $postId,
            'timestamp' => time(),
            'signals'   => [],
            'scores'    => [],
            'issues'    => [],
        ];

        if ($postId > 0) {
            $post = get_post($postId);
            if ($post && !is_wp_error($post)) {
                $content = (string) ($post->post_content ?? '');
                $title   = (string) ($post->post_title ?? '');

                $wordCount = str_word_count(wp_strip_all_tags($content));
                $analysis['signals']['title_length'] = mb_strlen($title);
                $analysis['signals']['content_words'] = $wordCount;

                // Very small heuristic score (read-only; purely observational).
                $analysis['scores']['content_density'] = min(100, (int) round($wordCount / 10));

                if ($wordCount < 200) {
                    $analysis['issues'][] = 'Низький обсяг контенту (менше 200 слів).';
                }
                if (mb_strlen($title) < 20) {
                    $analysis['issues'][] = 'Короткий заголовок (менше 20 символів).';
                }
            } else {
                $analysis['issues'][] = 'Сторінка для аналізу не знайдена.';
            }
        } else {
            $analysis['issues'][] = 'post_id відсутній у payload.';
        }

        $stored = $this->results->storeOnce($intent->id(), $postId, $analysis);
        if (!$stored) {
            return ExecutionResultDTO::fail('Не вдалося зберегти результат аналізу.');
        }

        return ExecutionResultDTO::ok('Аналіз виконано (read-only).', ['post_id' => $postId]);
    }
}
