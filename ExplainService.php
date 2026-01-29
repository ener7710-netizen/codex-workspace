<?php
declare(strict_types=1);

namespace SEOJusAI\Explain;

use SEOJusAI\AI\DecisionContract;

defined('ABSPATH') || exit;

/**
 * ExplainService
 *
 * ВАЖЛИВО:
 * - НЕ приймає рішень
 * - НЕ інтерпретує AI
 * - НЕ додає дефолтів
 * - ТІЛЬКИ транслює DecisionContract у human-readable форму
 */
final class ExplainService {

	/**
	 * Побудова пояснення для людини.
	 *
	 * @param array<string,mixed> $decision (DecisionContract)
	 * @param array<string,mixed> $context
	 */
	public function build(array $decision, array $context): array {

		if ( ! DecisionContract::validate($decision) ) {
			return [];
		}

		$meta = $decision['meta'];

		return [
			'summary'   => (string) ($meta['summary'] ?? ''),
			'risk'      => (string) ($meta['risk'] ?? 'unknown'),
			'confidence'=> (float) ($meta['confidence'] ?? 0),
			'reasoning' => (string) ($meta['reasoning'] ?? ''),
			'actions'   => $decision['actions'],
			'context'   => [
				'post_id' => (int) ($context['post_id'] ?? 0),
				'source'  => (string) ($context['source'] ?? 'unknown'),
			],
		];
	}
}
