<?php
declare(strict_types=1);

namespace SEOJusAI\Executors;

defined('ABSPATH') || exit;

interface ExecutorInterface {

	/**
	 * Виконати дію.
	 */
	public function apply(array $task): bool;

	/**
	 * Чи підтримує цей executor даний тип задачі.
	 */
	public function supports(string $type): bool;
}
