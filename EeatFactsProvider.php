<?php
declare(strict_types=1);

namespace SEOJusAI\Analyze;

use SEOJusAI\Eeat\EeatRepository;

defined('ABSPATH') || exit;

final class EeatFactsProvider {

	public function build(int $post_id): array {

		$eeat = EeatRepository::get($post_id);

		return [
			'author_present'     => !empty($eeat['author']),
			'experience_years'   => (int) ($eeat['experience'] ?? 0),
			'credentials'        => (string) ($eeat['credentials'] ?? ''),
			'trust_signals'      => (string) ($eeat['trust'] ?? ''),
			'completeness_score' => count(array_filter($eeat)),
		];
	}
}
