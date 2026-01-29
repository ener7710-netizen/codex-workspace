<?php
declare(strict_types=1);

namespace SEOJusAI\Experiments;

use SEOJusAI\Input\Input;

defined('ABSPATH') || exit;

/**
 * Простий, але робочий репозиторій експериментів (A/B) через options.
 * Безпечний: не змінює контент поста, працює на UI-шарі.
 */
final class ExperimentsRepository {

	private const OPTION = 'seojusai_experiments';

	/**
	 * @return array<int,array<string,mixed>>
	 */
	public function all(): array {
		$raw = get_option(self::OPTION, []);
		return is_array($raw) ? $raw : [];
	}

	/**
	 * @return array<int,array<string,mixed>>
	 */
	public function active(): array {
		$all = $this->all();
		return array_values(array_filter($all, static fn($e) => isset($e['status']) && $e['status'] === 'running'));
	}

	public function save_all(array $items): void {
		update_option(self::OPTION, array_values($items), false);
	}

	public function upsert(array $exp): array {
		$all = $this->all();
		$id = isset($exp['id']) ? (int)$exp['id'] : 0;
		if ($id <= 0) {
			$id = time(); // deterministic enough for admin create
			$exp['id'] = $id;
		}
		$found = false;
		foreach ($all as $i => $e) {
			if ((int)($e['id'] ?? 0) === $id) {
				$all[$i] = $exp;
				$found = true;
				break;
			}
		}
		if (!$found) {
			$all[] = $exp;
		}
		$this->save_all($all);
		return $exp;
	}

	public function set_status(int $id, string $status): void {
		$all = $this->all();
		foreach ($all as $i => $e) {
			if ((int)($e['id'] ?? 0) === $id) {
				$all[$i]['status'] = $status;
				break;
			}
		}
		$this->save_all($all);
	}
}
