<?php
declare(strict_types=1);

namespace SEOJusAI\Analyze\Intent;

defined('ABSPATH') || exit;

/**
 * CannibalizationDetector
 *
 * На базі GSC-рядків {query,page,impressions,clicks,position}
 * виявляє запити, де 2+ сторінки отримують суттєві покази.
 */
final class CannibalizationDetector {

	/**
	 * @param array<int,array<string,mixed>> $gsc_rows
	 * @return array<int,array<string,mixed>>
	 */
	public function detect(array $gsc_rows): array {
		$by_query = [];
		foreach ($gsc_rows as $r) {
			$q = (string) ($r['query'] ?? '');
			$p = (string) ($r['page'] ?? '');
			$impr = (float) ($r['impressions'] ?? 0);
			$clicks = (float) ($r['clicks'] ?? 0);
			$pos = (float) ($r['position'] ?? 0);
			if ($q === '' || $p === '' || $impr <= 0) { continue; }

			$by_query[$q] ??= [];
			$by_query[$q][$p] ??= ['page'=>$p,'impressions'=>0.0,'clicks'=>0.0,'pos_sum'=>0.0,'pos_w'=>0.0];
			$by_query[$q][$p]['impressions'] += $impr;
			$by_query[$q][$p]['clicks'] += $clicks;
			$by_query[$q][$p]['pos_sum'] += ($pos * max(1.0, $impr));
			$by_query[$q][$p]['pos_w'] += max(1.0, $impr);
		}

		$out = [];
		foreach ($by_query as $q => $pages) {
			$items = array_values($pages);
			foreach ($items as &$it) {
				$it['position'] = $it['pos_w'] > 0 ? ($it['pos_sum'] / $it['pos_w']) : 0.0;
				unset($it['pos_sum'], $it['pos_w']);
			}
			unset($it);
			usort($items, fn($a,$b) => ($b['impressions'] <=> $a['impressions']));

			if (count($items) < 2) { continue; }

			$total = array_sum(array_map(fn($x)=>(float)$x['impressions'], $items));
			$top1 = $items[0];
			$top2 = $items[1];

			// Поріг: обидві сторінки мають >= 15% показів, і топ2 не "дріб'язок"
			if ($total <= 0) { continue; }
			$share1 = $top1['impressions'] / $total;
			$share2 = $top2['impressions'] / $total;
			if ($share1 >= 0.15 && $share2 >= 0.15 && $top2['impressions'] >= 20) {
				$out[] = [
					'query' => $q,
					'total_impressions' => $total,
					'pages' => array_slice($items, 0, 5),
					'shares' => ['top1' => $share1, 'top2' => $share2],
				];
			}
		}

		return $out;
	}
}
