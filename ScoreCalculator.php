<?php
declare(strict_types=1);

namespace SEOJusAI\ContentScore;

defined('ABSPATH') || exit;

final class ScoreCalculator {

	public const META_KEY_SCORE = '_seojusai_content_score';
	public const META_KEY_BREAKDOWN = '_seojusai_content_score_breakdown';

	public function calculate(int $post_id): array {
		$title = (string) get_the_title($post_id);
		$content = (string) get_post_field('post_content', $post_id);
		$content_plain = trim(wp_strip_all_tags($content));
		$word_count = $content_plain === '' ? 0 : preg_match_all('~\p{L}+~u', $content_plain, $m);

		$has_h1 = preg_match('~<h1[^>]*>.*?</h1>~isu', (string) get_post_field('post_content', $post_id)) === 1;
		$images = preg_match_all('~<img\s[^>]*>~isu', (string) get_post_field('post_content', $post_id), $imgm);
		$missing_alt = 0;
		if ($images) {
			foreach ($imgm[0] as $tag) {
				if (!preg_match('~\salt\s*=\s*(["\']).*?\1~isu', $tag)) {
					$missing_alt++;
				}
			}
		}

		$internal_links = 0;
		if ($content_plain !== '') {
			$home = preg_quote((string) home_url(), '~');
			$internal_links = preg_match_all('~<a\s[^>]*href=(["\'])(https?:)?//' . $home . '[^"\']*\1~isu', (string) get_post_field('post_content', $post_id), $lm);
		}

		$issues = [];
		$score = 100;

		$tl = mb_strlen(trim($title));
		if ($tl < 20) { $issues[] = ['key'=>'title_short','label'=>__('Заголовок сторінки занадто короткий', 'seojusai'), 'impact'=>-10]; $score -= 10; }
		if ($tl > 65) { $issues[] = ['key'=>'title_long','label'=>__('Заголовок сторінки занадто довгий', 'seojusai'), 'impact'=>-10]; $score -= 10; }

		if (!$has_h1) { $issues[] = ['key'=>'missing_h1','label'=>__('На сторінці відсутній H1', 'seojusai'), 'impact'=>-10]; $score -= 10; }

		if ($word_count < 300) { $issues[] = ['key'=>'thin_content','label'=>__('Мало контенту (менше 300 слів)', 'seojusai'), 'impact'=>-15]; $score -= 15; }
		elseif ($word_count < 700) { $issues[] = ['key'=>'content_ok','label'=>__('Контент середній (700+ слів дає кращий потенціал)', 'seojusai'), 'impact'=>-5]; $score -= 5; }

		if ($internal_links < 2) { $issues[] = ['key'=>'few_internal_links','label'=>__('Недостатньо внутрішніх посилань (потрібно 2+)', 'seojusai'), 'impact'=>-10]; $score -= 10; }

		if ($missing_alt > 0) { $issues[] = ['key'=>'missing_alt','label'=>sprintf(__('Є зображення без ALT: %d', 'seojusai'), $missing_alt), 'impact'=>-5]; $score -= 5; }

		$score = max(0, min(100, $score));

		return [
			'score' => $score,
			'word_count' => (int) $word_count,
			'internal_links' => (int) $internal_links,
			'missing_alt' => (int) $missing_alt,
			'issues' => $issues,
		];
	}

	public function persist(int $post_id): array {
		$result = $this->calculate($post_id);
		update_post_meta($post_id, self::META_KEY_SCORE, (string) $result['score']);
		update_post_meta($post_id, self::META_KEY_BREAKDOWN, wp_json_encode($result, JSON_UNESCAPED_UNICODE));
		return $result;
	}

	public static function load(int $post_id): array {
		$json = (string) get_post_meta($post_id, self::META_KEY_BREAKDOWN, true);
		$data = $json ? json_decode($json, true) : null;
		return is_array($data) ? $data : [];
	}
}
