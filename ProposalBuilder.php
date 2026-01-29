<?php
declare(strict_types=1);

namespace SEOJusAI\Proposals;

use SEOJusAI\ContentScore\ScoreCalculator;
use SEOJusAI\Meta\MetaRepository;

defined('ABSPATH') || exit;

final class ProposalBuilder {

	public const META_KEY = '_seojusai_proposals';

	public function build(int $post_id): array {
		$items = [];

		$score = ScoreCalculator::load($post_id);
		$issues = $score['issues'] ?? [];
		$repo = new MetaRepository();
		$meta = $repo->get($post_id);

		// Meta title/desc
		if (trim($meta['title'] ?? '') === '') {
			$items[] = (new ProposalItem(
				'meta_title',
				__('Додати SEO-заголовок', 'seojusai'),
				__('Заголовок впливає на CTR. Рекомендуємо сформувати Title 50–60 символів з ключовою фразою на початку.', 'seojusai'),
				'low',
				'low',
				8,
				['suggest' => $this->suggest_title($post_id)]
			))->to_array();
		}
		if (trim($meta['description'] ?? '') === '') {
			$items[] = (new ProposalItem(
				'meta_description',
				__('Додати meta description', 'seojusai'),
				__('Опис підвищує CTR. Рекомендуємо 140–160 символів, з вигодою та уточненням для клієнта.', 'seojusai'),
				'low',
				'low',
				6,
				['suggest' => $this->suggest_description($post_id)]
			))->to_array();
		}

		// Content issues
		foreach ($issues as $issue) {
			$key = $issue['key'] ?? '';
			if ($key === 'thin_content') {
				$items[] = (new ProposalItem(
					'content_expand',
					__('Розширити контент', 'seojusai'),
					__('Додайте блоки: відповіді на часті питання, алгоритм дій, документи, строки, ризики та практика. Це збільшує релевантність і покриття інтенцій.', 'seojusai'),
					'high',
					'low',
					12
				))->to_array();
			}
			if ($key === 'few_internal_links') {
				$items[] = (new ProposalItem(
					'internal_links',
					__('Додати внутрішні посилання', 'seojusai'),
					__('Додайте 2–5 посилань на релевантні послуги/статті (з природними анкорами). Це посилює тематичний кластер і розподіл ваги.', 'seojusai'),
					'low',
					'low',
					8
				))->to_array();
			}
			if ($key === 'missing_h1') {
				$items[] = (new ProposalItem(
					'add_h1',
					__('Додати H1', 'seojusai'),
					__('Додайте один чіткий H1 з основною ключовою фразою. Це покращує зрозумілість для пошукових систем.', 'seojusai'),
					'low',
					'low',
					6
				))->to_array();
			}
		}

		// Schema suggestion (simple)
		$schema_type = (string) get_post_meta($post_id, '_seojusai_schema_type', true);
		if ($schema_type === '') {
			$items[] = (new ProposalItem(
				'schema',
				__('Додати Schema.org', 'seojusai'),
				__('Рекомендуємо додати базову розмітку (Article або LegalService) та, за потреби, FAQ. Це підвищує якість сніпетів.', 'seojusai'),
				'medium',
				'low',
				7
			))->to_array();
		}

		usort($items, static fn($a,$b)=> ($b['expected_impact']??0) <=> ($a['expected_impact']??0));
		return $items;
	}

	public function persist(int $post_id): array {
		$items = $this->build($post_id);
		update_post_meta($post_id, self::META_KEY, wp_json_encode($items, JSON_UNESCAPED_UNICODE));
		return $items;
	}

	public static function load(int $post_id): array {
		$json = (string) get_post_meta($post_id, self::META_KEY, true);
		$data = $json ? json_decode($json, true) : null;
		return is_array($data) ? $data : [];
	}

	private function suggest_title(int $post_id): string {
		$t = (string) get_the_title($post_id);
		return trim($t) !== '' ? $t : __('Юридична послуга — консультація та захист', 'seojusai');
	}

	private function suggest_description(int $post_id): string {
		$content = (string) get_post_field('post_content', $post_id);
		$plain = trim(wp_strip_all_tags($content));
		if ($plain === '') {
			return __('Коротко поясніть, що отримує клієнт: консультація, підготовка документів, супровід у суді. Додайте строк і місто.', 'seojusai');
		}
		$plain = preg_replace('~\s+~u', ' ', $plain);
		return mb_substr($plain, 0, 155);
	}
}
