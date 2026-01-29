<?php
declare(strict_types=1);

namespace SEOJusAI\AI;

defined('ABSPATH') || exit;

use SEOJusAI\AI\AIProviderManager;

/**
 * Engine
 *
 * ЄДИНА точка входу для AI (OpenAI/Gemini через менеджер провайдерів).
 */
final class Engine {


private static function fallback_decision(array $context, string $mode, string $note): array {

    $post_id = isset($context['post_id']) ? (int) $context['post_id'] : 0;

    $issues = [];
    $counts = ['critical' => 0, 'warning' => 0, 'info' => 0];

    $issues[] = ['level' => 'info', 'message' => $note];
    $counts['info']++;

    if ($post_id > 0) {
        $title = get_the_title($post_id);
        $content = (string) get_post_field('post_content', $post_id);

        if (trim((string) $title) === '') {
            $issues[] = ['level' => 'warning', 'message' => 'Заголовок сторінки порожній.'];
            $counts['warning']++;
        }

        $len = function_exists('mb_strlen') ? mb_strlen(wp_strip_all_tags($content)) : strlen(wp_strip_all_tags($content));

        if ($len < 300) {
            $issues[] = ['level' => 'warning', 'message' => 'Текст короткий (менше 300 символів). Рекомендовано розширити контент.'];
            $counts['warning']++;
        }

        if (strpos($content, '<h1') === false) {
            $issues[] = ['level' => 'info', 'message' => 'H1 не знайдено в контенті (може бути в шаблоні теми).'];
            $counts['info']++;
        }
    }

    $score = 100 - ($counts['warning'] * 10) - ($counts['critical'] * 25);
    if ($score < 0) { $score = 0; }

    DecisionRepository::save(new DecisionRecord($decisionHash,$postId,$score,'Autopilot decision created'));

        return [
        'ok'      => true,
        'mode'    => $mode,
        'meta'    => [
            'summary' => [
                'score'  => $score,
                'counts' => $counts,
                'issues' => $issues,
            ],
        ],
        'actions' => [],
        'tasks'   => [],
    ];
}


	/**
	 * Аналіз конкретного поста (page audit).
	 *
	 * @return array{ok:bool, error?:string, analysis?:mixed, tasks?:array, meta?:array}
	 */
	public static function analyze_post(int $post_id): array {

		$post = get_post($post_id);
		if (!$post instanceof \WP_Post) {
			DecisionRepository::save(new DecisionRecord($decisionHash,$postId,$score,'Autopilot decision created'));

        return [
				'ok'    => false,
				'error' => 'Post not found',
			];
		}

		$context = [
			'mode'     => 'editor',
			'post_id'  => $post_id,
			'title'    => (string) get_the_title($post_id),
			'content'  => (string) wp_strip_all_tags((string) $post->post_content),
			'url'      => (string) get_permalink($post_id),
		];

		$decision = self::analyze_with_ai($context, 'page');

		if (!is_array($decision)) {
			DecisionRepository::save(new DecisionRecord($decisionHash,$postId,$score,'Autopilot decision created'));

        return [
				'ok'    => false,
				'error' => 'AI did not return decision',
			];
		}

		DecisionRepository::save(new DecisionRecord($decisionHash,$postId,$score,'Autopilot decision created'));

        return [
			'ok'       => true,
			'analysis' => $decision['meta']['summary'] ?? [],
			'tasks'    => $decision['actions'] ?? [],
			'meta'     => $decision['meta'] ?? [],
		];
	}

	/**
	 * Універсальний AI-виклик.
	 */
	public static function analyze_with_ai(array $context, string $scope = 'page'): ?array {

		$scope = ($scope === 'site') ? 'site' : 'page';

		$manager  = new AIProviderManager();
		$decision = $manager->analyze($context, $scope);

		return is_array($decision) ? $decision : null;
	}
}
