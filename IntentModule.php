<?php
declare(strict_types=1);

namespace SEOJusAI\Modules;

use SEOJusAI\Core\Contracts\ModuleInterface;
use SEOJusAI\Core\Kernel;

defined('ABSPATH') || exit;

/**
 * IntentModule
 *
 * Модуль інтенцій та канібалізації запитів на основі GSC snapshots.
 * ВАЖЛИВО: модуль не виконує аналіз синхронно — лише реєструє поверхні та тригери.
 */
final class IntentModule implements ModuleInterface {

	public function get_slug(): string {
		return 'intent';
	}

	public function register(Kernel $kernel): void {
		// Немає окремої реєстрації сервісів у Kernel для цього модуля (використовує існуючу чергу задач).
	}

	public function init(Kernel $kernel): void {
		// UI в адмінці підключається через Menu. Тут ми лише гарантуємо, що модуль може бути увімкнений/вимкнений системою модулів.
	}
}
