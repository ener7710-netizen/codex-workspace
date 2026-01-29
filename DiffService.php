<?php
declare(strict_types=1);

namespace SEOJusAI\Snapshots;

defined('ABSPATH') || exit;

final class DiffService {

	/**
	 * Простий текстовий diff (без сторонніх бібліотек)
	 */
	public function diff(string $before, string $after): array {

		$before_lines = explode("\n", $before);
		$after_lines  = explode("\n", $after);

		$diff = [];

		foreach ($after_lines as $i => $line) {
			if (!isset($before_lines[$i])) {
				$diff[] = ['type' => 'add', 'line' => $line];
				continue;
			}

			if ($before_lines[$i] !== $line) {
				$diff[] = [
					'type'   => 'change',
					'before'=> $before_lines[$i],
					'after' => $line,
				];
			}
		}

		foreach ($before_lines as $i => $line) {
			if (!isset($after_lines[$i])) {
				$diff[] = ['type' => 'remove', 'line' => $line];
			}
		}

		return $diff;
	}
}
