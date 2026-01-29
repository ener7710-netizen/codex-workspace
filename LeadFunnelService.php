<?php
declare(strict_types=1);

namespace SEOJusAI\LeadFunnel;

use SEOJusAI\Input\Input;
use SEOJusAI\Competitive\MarketRules;

defined('ABSPATH') || exit;

/**
 * LeadFunnelService (Legal)
 * Реальна (не заглушка) евристика для рекомендацій CTA.
 * НІКОЛИ не змінює контент автоматично — лише аналіз + підказки.
 */
final class LeadFunnelService {

	/**
	 * @return array{intent:string,cta:string,impact:int,risk:string,issues:array<string>,signals:array<string>}
	 */
	public function analyze_post(int $post_id): array {
		$post = get_post($post_id);
		if (!$post || $post->post_status !== 'publish') {
			return [
				'intent' => LeadIntent::INFO,
				'cta' => __('Поставити запитання юристу', 'seojusai'),
				'impact' => 10,
				'risk' => 'low',
				'issues' => [__('Сторінка недоступна або не опублікована.', 'seojusai')],
				'signals' => [],
			];
		}

		$content = (string) $post->post_content;
		$title = (string) $post->post_title;
		$signals = [];
		$issues = [];

		$has_tel = (bool) preg_match('~href=["\']tel:~i', $content);
		$has_form = (bool) preg_match('~\[contact-form|\[wpforms|\[gravityform|\[form~i', $content);
		$has_contact_block = (bool) preg_match('~(телефон|дзвін|консультац|звернутися)~iu', $content);

		if ($has_tel) { $signals[] = 'tel'; }
		if ($has_form) { $signals[] = 'form'; }
		if ($has_contact_block) { $signals[] = 'contact_text'; }

		$intent = $this->detect_intent($title, $content);
		$page_type = ($intent === LeadIntent::INFO) ? 'info' : 'problem';
		$market_allows_cta = MarketRules::allow_soft_cta_for($page_type);

		$cta = match($intent) {
			LeadIntent::URGENT => __('Зв’язатися з адвокатом', 'seojusai'),
			LeadIntent::CONSULT => __('Отримати консультацію', 'seojusai'),
			default => __('Поставити запитання юристу', 'seojusai'),
		};

		// Market-calibrated issues: не нав’язуємо CTA там, де ринок це не робить.
		if ($market_allows_cta) {
			if (!$has_tel) { $issues[] = __('Немає явного CTA “Телефон” (tel:).', 'seojusai'); }
			if (!$has_form) { $issues[] = __('Немає форми звернення (shortcode форми).', 'seojusai'); }
			if (!$has_contact_block) { $issues[] = __('Немає пояснення, як звернутися (контактний блок/текст).', 'seojusai'); }
		} else {
			$issues[] = __('Ринкові сигнали: для цього типу сторінки soft CTA не є обов’язковим у конкурентів. Плагін не наполягає на CTA.', 'seojusai');
		}

		// Impact heuristic: problem pages виграють від soft CTA лише якщо ринок це підтверджує.
		$impact = 20;
		if ($intent === LeadIntent::URGENT) { $impact += 35; }
		if ($intent === LeadIntent::CONSULT) { $impact += 25; }

		if ($market_allows_cta) {
			if ($has_tel) { $impact += 10; }
			if ($has_form) { $impact += 10; }
			if ($has_contact_block) { $impact += 5; }
		} else {
			$impact -= 10;
		}
		$impact = max(0, min(100, $impact));

		return [
			'intent' => $intent,
			'cta' => $cta,
			'impact' => $impact,
			'risk' => 'low',
			'issues' => $issues,
			'signals' => array_values(array_merge($signals, [
				'market_allows_cta:' . ($market_allows_cta ? 'yes' : 'no'),
				'page_type:' . $page_type,
			])),
		];
	}

	public function detect_intent(string $title, string $content): string {
		$hay = mb_strtolower($title . ' ' . wp_strip_all_tags($content));
		if (preg_match('~(обшук|затрим|арешт|підозр|повістк|терміново|негайно)~u', $hay)) {
			return LeadIntent::URGENT;
		}
		if (preg_match('~(консультац|послуг|захист|адвокат|представництв|супровід)~u', $hay)) {
			return LeadIntent::CONSULT;
		}
		return LeadIntent::INFO;
	}

	/**
	 * @return array<int,array<string,mixed>>
	 */
	public function top_pages_by_impact(int $limit = 20): array {
		$limit = max(1, min(100, $limit));
		$q = new \WP_Query([
			'post_type' => ['page', 'post'],
			'post_status' => 'publish',
			'posts_per_page' => 200, // scan cap for safety
			'orderby' => 'date',
			'order' => 'DESC',
			'fields' => 'ids',
		]);

		$rows = [];
		foreach ((array) $q->posts as $pid) {
			$r = $this->analyze_post((int) $pid);
			$rows[] = [
				'post_id' => (int) $pid,
				'title' => (string) get_the_title((int)$pid),
				'intent' => $r['intent'],
				'cta' => $r['cta'],
				'impact' => $r['impact'],
				'issues' => $r['issues'],
			];
		}
		usort($rows, static fn($a,$b) => (int)$b['impact'] <=> (int)$a['impact']);
		return array_slice($rows, 0, $limit);
	}
}
