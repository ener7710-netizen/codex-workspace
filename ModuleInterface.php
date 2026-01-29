<?php
declare(strict_types=1);

namespace SEOJusAI\Core\Contracts;

use SEOJusAI\Core\Kernel;

defined('ABSPATH') || exit;

/**
 * Єдиний контракт для системних модулів SEOJusAI.
 *
 * НЕ ламає існуючі init()-модулі.
 * Використовується для нових модулів (AI, Schema, Autopilot, Executors).
 */
interface ModuleInterface {

	/**
	 * Унікальний slug модуля (ai, schema, autopilot, serp, etc)
	 */
	public function get_slug(): string;

	/**
	 * Реєстрація модуля в Kernel
	 */
	public function register(Kernel $kernel): void;

	/**
	 * Ініціалізація (аналог init(), але типізована)
	 */
	public function init(Kernel $kernel): void;
}
