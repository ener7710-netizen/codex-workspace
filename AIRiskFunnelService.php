<?php
declare(strict_types=1);

namespace SEOJusAI\AIRiskFunnel;

use SEOJusAI\AIRiskFunnel\Analyzer\RiskSignalAnalyzer;
use SEOJusAI\AIRiskFunnel\Analyzer\ConsequenceAnalyzer;
use SEOJusAI\AIRiskFunnel\Analyzer\DecisionGapAnalyzer;
use SEOJusAI\AIRiskFunnel\Scoring\ClientIntentScore;
use SEOJusAI\AIRiskFunnel\Contracts\AIRiskContract;
use SEOJusAI\Explain\ExplainRepository;

defined('ABSPATH') || exit;

final class AIRiskFunnelService {

    public const TASK_ANALYZE_POST = 'risk_funnel_analyze';

    /**
     * Аналізує пост і повертає структуру контракту.
     * @return array<string,mixed>
     */
    public function analyze_post(int $post_id): array {
        $post_id = max(0, (int)$post_id);
        $res = AIRiskContract::empty();
        $res['post_id'] = $post_id;

        $post = $post_id > 0 ? get_post($post_id) : null;
        if (!$post) {
            $res['ok'] = false;
            $res['recommendations'][] = [
                'level' => 'error',
                'message' => __('Пост не знайдено.', 'seojusai'),
            ];
            return $res;
        }

        $text = $this->build_text($post);

        $riskA = (new RiskSignalAnalyzer())->analyze($text);
        $conA  = (new ConsequenceAnalyzer())->analyze($text);
        $gapA  = (new DecisionGapAnalyzer())->analyze($text);

        $score = (new ClientIntentScore())->score($riskA['score'], $conA['score'], $gapA['score']);

        $res['score'] = [
            'client_intent' => (int)$score['client_intent'],
            'risk_signal'   => (float)$riskA['score'],
            'consequences'  => (float)$conA['score'],
            'decision_gap'  => (float)$gapA['score'],
        ];

        $res['signals'] = [
            'risk_terms' => (array)($riskA['terms']['risk_terms'] ?? []),
            'sanctions_terms' => (array)($riskA['terms']['sanctions_terms'] ?? []),
            'process_terms' => (array)($riskA['terms']['process_terms'] ?? []),
            'consequence_terms' => (array)($conA['terms'] ?? []),
            'decision_gap_terms' => (array)($gapA['terms'] ?? []),
        ];

        $res['recommendations'] = $this->recommendations($res['score'], $res['signals']);

        // persist meta for later usage in Learning/AI
        update_post_meta($post_id, '_seojusai_client_intent_score', (int)$res['score']['client_intent']);

        // save explain snapshot (system)
        $hash = 'riskfunnel:' . md5((string)$post->post_modified_gmt . ':' . (string)$post_id);
        $repo = new ExplainRepository();
        $repo->save('post', $post_id, $hash, [
            'type' => 'ai_risk_funnel',
            'scope' => 'criminal',
            'score' => $res['score'],
            'signals' => $res['signals'],
            'recommendations' => $res['recommendations'],
        ], 'low', 'system', 'risk_funnel', null, null, 0);

        return $res;
    }

    /** @return array<int,array<string,mixed>> */
    private function recommendations(array $score, array $signals): array {
        $rec = [];

        $risk = (float)($score['risk_signal'] ?? 0);
        $cons = (float)($score['consequences'] ?? 0);
        $gap  = (float)($score['decision_gap'] ?? 0);
        $intent = (int)($score['client_intent'] ?? 0);

        if ($risk < 0.55) {
            $rec[] = [
                'level' => 'high',
                'message' => __('Додайте чіткі “ризикові тригери” кримінального процесу (підозра/обшук/допит) та посилання на релевантні норми.', 'seojusai'),
            ];
        }
        if (empty($signals['sanctions_terms'])) {
            $rec[] = [
                'level' => 'medium',
                'message' => __('Додайте блок про можливі наслідки/санкції (штраф/позбавлення волі/судимість) без перебільшень — це підвищує ясність для читача.', 'seojusai'),
            ];
        }
        if ($cons < 0.55) {
            $rec[] = [
                'level' => 'medium',
                'message' => __('Додайте розділ “Що буде, якщо нічого не робити” + “типові помилки” (нейтрально, без залякування).', 'seojusai'),
            ];
        }
        if ($gap < 0.45) {
            $rec[] = [
                'level' => 'high',
                'message' => __('Додайте логічний блок “чому без фахівця часто помиляються” (наприклад: не підписувати/не давати показання без захисника). Це не реклама, а юридична безпека.', 'seojusai'),
            ];
        }

        if ($intent >= 80) {
            $rec[] = [
                'level' => 'low',
                'message' => __('Сторінка має високий потенціал конверсії. Підтримуйте нейтральний тон і додайте короткий FAQ з практичними питаннями.', 'seojusai'),
            ];
        } elseif ($intent <= 40) {
            $rec[] = [
                'level' => 'medium',
                'message' => __('Низький “intent”: сторінка пояснює тему, але не веде читача до рішення. Додайте чіткі умови/сценарії (якщо → то) і практичні кроки.', 'seojusai'),
            ];
        }

        return $rec;
    }

    private function build_text(\WP_Post $post): string {
        $title = (string) $post->post_title;
        $content = wp_strip_all_tags((string)$post->post_content);
        $content = preg_replace('/\s+/', ' ', $content) ?? $content;

        return trim($title . "

" . $content);
    }
}
