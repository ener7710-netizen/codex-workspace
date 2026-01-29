<?php
declare(strict_types=1);

namespace SEOJusAI\Core;

use SEOJusAI\ContentScore\ScoreCalculator;
use SEOJusAI\Proposals\ProposalBuilder;
use SEOJusAI\Utils\Logger;

defined('ABSPATH') || exit;

final class AnalysisPersistence {

	public static function register(): void {
		add_action('seojusai/analysis/complete', [self::class, 'persist'], 20, 1);
	}

	/**
	 * Persist analysis artifacts into post meta for UI consumption.
	 *
	 * @param array $analysis Analysis payload produced by PageAuditRunner.
	 */
	public static function persist(array $analysis): void {
		$post_id = isset($analysis['post_id']) ? (int) $analysis['post_id'] : 0;
		if ($post_id <= 0) {
			return;
		}

		try {
			// Score + issues breakdown.
			if (isset($analysis['content_score']) && is_array($analysis['content_score'])) {
				$score = isset($analysis['content_score']['score']) ? (int) $analysis['content_score']['score'] : null;
				$issues = isset($analysis['content_score']['issues']) && is_array($analysis['content_score']['issues'])
					? $analysis['content_score']['issues']
					: [];

				if ($score !== null) {
					$score = max(0, min(100, $score));
					update_post_meta($post_id, '_seojusai_score', $score);
					update_post_meta($post_id, ScoreCalculator::META_KEY_SCORE, (string) $score);
				}

				$breakdown = [
					'score' => $score !== null ? $score : 0,
					'issues' => $issues,
					'generated_at' => isset($analysis['timestamp']) ? (int) $analysis['timestamp'] : time(),
				];

				update_post_meta($post_id, ScoreCalculator::META_KEY_BREAKDOWN, wp_json_encode($breakdown));
				update_post_meta($post_id, '_seojusai_issues_taxonomy', wp_json_encode(self::taxonomy($issues)));
			}

			// Proposals list.
			if (class_exists(ProposalBuilder::class)) {
				$proposals = (new ProposalBuilder())->build($post_id);
				update_post_meta($post_id, ProposalBuilder::META_KEY, wp_json_encode($proposals));
			}

		} catch (\Throwable $e) {
			if (class_exists(Logger::class)) {
				Logger::error('analysis_persist_failed', ['post_id' => $post_id, 'error' => $e->getMessage()]);
			}
		}
	}

	/**
	 * Convert raw issues into UX taxonomy: critical / recommendations / content.
	 *
	 * @param array $issues
	 * @return array<string,array<int,array>>
	 */
	private static function taxonomy(array $issues): array {
		$tax = [
			'critical' => [],
			'recommendations' => [],
			'content' => [],
		];

		foreach ($issues as $issue) {
			if (!is_array($issue)) {
				continue;
			}
			$key = isset($issue['key']) ? (string) $issue['key'] : '';
			$impact = isset($issue['impact']) ? (int) $issue['impact'] : 0;

			$bucket = 'recommendations';

			// Critical: structural blockers and large negative impact.
			if (in_array($key, ['missing_h1', 'thin_content'], true) || $impact <= -15) {
				$bucket = 'critical';
			}

			// Content optimization: medium impact content-related items.
			if (in_array($key, ['content_ok', 'few_internal_links'], true)) {
				$bucket = 'content';
			}

			$tax[$bucket][] = [
				'key' => $key,
				'label' => isset($issue['label']) ? (string) $issue['label'] : '',
				'impact' => $impact,
				'tooltip' => self::tooltip_for($key),
			];
		}

		return $tax;
	}

	private static function tooltip_for(string $key): string {
		switch ($key) {
			case 'missing_h1':
				return __('H1 — головний заголовок сторінки. Він задає тему та допомагає Google зрозуміти фокус сторінки.', 'seojusai');
			case 'thin_content':
				return __('Сторінка має замало контенту для покриття інтенції. Додайте FAQ, алгоритм дій, документи та практику.', 'seojusai');
			case 'few_internal_links':
				return __('Внутрішні посилання передають вагу та зшивають тематичні кластери. Додайте 2–5 релевантних посилань.', 'seojusai');
			case 'title_short':
				return __('Короткий заголовок знижує релевантність і CTR. Сформуйте Title 50–60 символів з ключовою фразою.', 'seojusai');
			case 'title_long':
				return __('Занадто довгий Title обрізається у видачі. Скоротіть до 50–60 символів, зберігаючи ключову фразу.', 'seojusai');
			default:
				return __('Деталі цього пункту формуються з аудиту контенту та структури сторінки.', 'seojusai');
		}
	}
}
