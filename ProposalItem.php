<?php
declare(strict_types=1);

namespace SEOJusAI\Proposals;

defined('ABSPATH') || exit;

final class ProposalItem {
	public function __construct(
		public string $type,
		public string $title,
		public string $details,
		public string $effort = 'medium',
		public string $risk = 'low',
		public int $expected_impact = 0,
		public array $payload = []
	) {}
	public function to_array(): array {
		return [
			'type' => $this->type,
			'title' => $this->title,
			'details' => $this->details,
			'effort' => $this->effort,
			'risk' => $this->risk,
			'expected_impact' => $this->expected_impact,
			'payload' => $this->payload,
		];
	}
}
