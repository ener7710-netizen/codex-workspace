<?php
declare(strict_types=1);

namespace SEOJusAI\Tasks;

use SEOJusAI\AI\ZeroShotClassifier;
use SEOJusAI\Domain\DecisionRecord;
use SEOJusAI\Repository\DecisionRepository;
use SEOJusAI\Repository\DecisionItemRepository;

defined('ABSPATH')||exit;

final class BuildDecisionTask {

    public function __invoke(array $payload): array {
        $post_id=(int)($payload['post_id']??0);
        if(!$post_id) return ['error'=>'no post'];

        $post=get_post($post_id);
        if(!$post) return ['error'=>'post not found'];

        $text=trim($post->post_title."\n".wp_strip_all_tags($post->post_content));

        $pageType=ZeroShotClassifier::classify($text,'page_type');
        $practice=ZeroShotClassifier::classify($text,'practice_area');
        $intent=ZeroShotClassifier::classify($text,'search_intent');

        $decision_hash=sha1($post_id.json_encode([$pageType,$practice,$intent]));

        $summary=sprintf(
            'Page type: %s (%.2f), Practice: %s (%.2f), Intent: %s (%.2f)',
            $pageType['label'],$pageType['confidence'],
            $practice['label'],$practice['confidence'],
            $intent['label'],$intent['confidence']
        );

        DecisionRepository::save(
            new DecisionRecord(
                $decision_hash,
                $post_id,
                min($pageType['confidence'],$practice['confidence'],$intent['confidence']),
                $summary,
                'planned'
            )
        );

        // Persist per-taxonomy predictions for self-training and review.
        DecisionItemRepository::add($decision_hash, $post_id, 'page_type', $pageType);
        DecisionItemRepository::add($decision_hash, $post_id, 'practice_area', $practice);
        DecisionItemRepository::add($decision_hash, $post_id, 'search_intent', $intent);

        return [
            'decision_hash'=>$decision_hash,
            'page_type'=>$pageType,
            'practice_area'=>$practice,
            'search_intent'=>$intent,
        ];
    }
}
