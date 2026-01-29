<?php
declare(strict_types=1);

namespace SEOJusAI\Opportunity;

use SEOJusAI\Snapshots\SnapshotRepository;
use SEOJusAI\ContentScore\ScoreCalculator;
use SEOJusAI\Analyze\Intent\IntentClassifier;
use SEOJusAI\Analyze\Intent\UrlClusterer;
use SEOJusAI\Analyze\Intent\CannibalizationDetector;

defined('ABSPATH') || exit;

/**
 * OpportunityEngine
 *
 * "SEO Director"-логіка: пріоритизація сторінок/кластерів на базі GSC.
 * Результат використовується в адмінці (Opportunity) та Autopilot.
 */
final class OpportunityEngine {

	/**
	 * @return array<string,mixed>
	 */
	public function compute(int $limit = 50): array {
		$site = (string) home_url('/');
		$site_id = (int) crc32($site);

		$repo = new SnapshotRepository();
		$latest = $repo->get_latest('gsc', $site_id, 1);
		if (empty($latest[0]['data']) || !is_array($latest[0]['data'])) {
			return [
				'pages' => [],
				'clusters' => [],
				'cannibalization' => [],
			];
		}

		/** @var array<int,array<string,mixed>> $rows */
		$rows = $latest[0]['data'];

		// 1) Cannibalization
		$cann = (new CannibalizationDetector())->detect($rows);

		// 2) Aggregate per page
		$pages = [];
		foreach ($rows as $row) {
			$page = (string) ($row['page'] ?? '');
			if ($page === '') { continue; }

			$clicks = (float) ($row['clicks'] ?? 0);
			$impr   = (float) ($row['impressions'] ?? 0);
			$pos    = (float) ($row['position'] ?? 0);
			$query  = (string) ($row['query'] ?? '');

			$pages[$page] ??= [
				'page' => $page,
				'clicks' => 0.0,
				'impressions' => 0.0,
				'pos_sum' => 0.0,
				'pos_w' => 0.0,
				'queries' => [],
			];
			$pages[$page]['clicks'] += $clicks;
			$pages[$page]['impressions'] += $impr;
			$pages[$page]['pos_sum'] += ($pos * max(1.0, $impr));
			$pages[$page]['pos_w'] += max(1.0, $impr);

			if ($query !== '') {
				$pages[$page]['queries'][$query] ??= 0.0;
				$pages[$page]['queries'][$query] += $impr;
			}
		}

		if (empty($pages)) {
			return [
				'pages' => [],
				'clusters' => [],
				'cannibalization' => $cann,
			];
		}

		$max_impr = max(array_map(fn($p) => (float) $p['impressions'], $pages));

		$score_calc = class_exists(ScoreCalculator::class) ? new ScoreCalculator() : null;
		$intent = new IntentClassifier();

		$items = [];
		foreach ($pages as $page => $p) {
			$avg_pos = $p['pos_w'] > 0 ? ($p['pos_sum'] / $p['pos_w']) : 0.0;

			arsort($p['queries']);
			$top_queries = array_slice(array_keys($p['queries']), 0, 5);
			$top_intent = $top_queries ? $intent->classify_query((string) $top_queries[0]) : 'informational';

			// Proximity: чим ближче до ТОП-10 (позиція 11-20 найкраща)
			$proximity = 0.0;
			if ($avg_pos > 0) {
				$proximity = max(0.0, min(1.0, (30.0 - $avg_pos) / 20.0)); // pos 10→1, pos 30→0
			}

			$demand = $max_impr > 0 ? ($p['impressions'] / $max_impr) : 0.0;

			// IntentValue: для legal_action/commercial підсилюємо
			$intent_value = match ($top_intent) {
				'legal_action' => 1.15,
				'commercial' => 1.10,
				'local' => 1.05,
				'navigational' => 0.95,
				default => 1.0,
			};

			// Effort: оцінимо через контент-скор (низький скор → більше роботи)
			$effort = 1.0;
			if ($score_calc) {
				$post_id = url_to_postid($page);
				if ($post_id > 0) {
					$cs = $score_calc->calculate($post_id);
					$effort = 1.0 + (max(0.0, 100.0 - (float)($cs['total'] ?? 0)) / 100.0); // 1..2
				}
			}

			$w = class_exists('SEOJusAI\\Learning\\WeightManager') ? (new \SEOJusAI\Learning\WeightManager())->get() : [
				'demand' => 1.0, 'proximity' => 1.0, 'intent' => 1.0, 'effort' => 1.0
			];

			$opportunity = (
				pow($demand, (float)($w['demand'] ?? 1.0)) *
				pow($proximity, (float)($w['proximity'] ?? 1.0)) *
				pow($intent_value, (float)($w['intent'] ?? 1.0))
			) / max(0.6, pow($effort, (float)($w['effort'] ?? 1.0)));

			$items[] = [
				'page' => $page,
				'clicks' => round($p['clicks'], 2),
				'impressions' => round($p['impressions'], 2),
				'position' => round($avg_pos, 2),
				'top_queries' => $top_queries,
				'intent' => $top_intent,
				'opportunity' => round($opportunity * 100, 2),
			];
		}

		usort($items, fn($a,$b) => ($b['opportunity'] <=> $a['opportunity']));
		$items = array_slice($items, 0, max(1, $limit));

		// 3) Clusters
		$clusterer = new UrlClusterer();
		$clusters = $clusterer->cluster(array_map(fn($x)=>$x['page'], $items));

		$cluster_items = [];
		foreach ($clusters as $cid => $urls) {
			$score = 0.0;
			$sum_impr = 0.0;
			foreach ($items as $it) {
				if (in_array($it['page'], $urls, true)) {
					$score += (float) $it['opportunity'];
					$sum_impr += (float) $it['impressions'];
				}
			}
			$cluster_items[] = [
				'cluster' => $cid,
				'pages' => $urls,
				'cluster_opportunity' => round($score, 2),
				'cluster_impressions' => round($sum_impr, 2),
			];
		}
		usort($cluster_items, fn($a,$b)=>($b['cluster_opportunity'] <=> $a['cluster_opportunity']));

		return [
			'pages' => $items,
			'clusters' => $cluster_items,
			'cannibalization' => $cann,
		];
	}
}
