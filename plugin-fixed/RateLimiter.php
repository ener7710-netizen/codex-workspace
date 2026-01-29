<?php
declare(strict_types=1);

namespace SEOJusAI\AI\Security;

defined('ABSPATH') || exit;

final class RateLimiter {

	private const OPTION_KEY = 'seojusai_ai_usage';

	private int $limit;
	private int $window;

	public function __construct(int $limit = 100, int $window = 3600) {
		$this->limit  = $limit;
		$this->window = $window;
	}

	public function allow(string $key): bool {

		$data = get_option(self::OPTION_KEY, []);
		$data = is_array($data) ? $data : [];

		$now = time();

		$bucket = $data[$key] ?? [
			'count' => 0,
			'start' => $now,
		];

		// reset window
		if (($now - $bucket['start']) > $this->window) {
			$bucket = [
				'count' => 0,
				'start' => $now,
			];
		}

		if ($bucket['count'] >= $this->limit) {
			return false;
		}

		$bucket['count']++;

		$data[$key] = $bucket;
		update_option(self::OPTION_KEY, $data, false);

		return true;
	}
}
