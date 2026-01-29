<?php
declare(strict_types=1);

namespace SEOJusAI\AI;

use SEOJusAI\Core\EmergencyStop;

defined('ABSPATH') || exit;

/**
 * AIKernel
 *
 * Легкий реєстратор AI-хуків + прямий запуск Gemini (опційно).
 * Працює без проксі й без “автоматичного застосування” змін (Human-in-the-Loop).
 */
final class AIKernel {

	private bool $registered = false;

	public function register(): void {
		if ($this->registered) {
			return;
		}
		$this->registered = true;

		/**
		 * Дозволяє модулю/іншим частинам ядра викликати AI-аналіз:
		 * do_action('seojusai/ai/analyze', $context, $scope);
		 */
		add_action('seojusai/ai/analyze', function (array $context, string $scope = 'page'): void {
			if (EmergencyStop::is_active()) {
				return;
			}
			// Нічого не виконуємо тут напряму — Engine/REST самі викликають.
		}, 10, 2);

		/**
		 * Прямий запуск Gemini (deep analysis).
		 * Повертає результат через фільтр (щоб не жорстко залежати від мережі/ключів).
		 */
		add_filter('seojusai/ai/run_gemini', function ($result, array $payload) {
			return $result;
		}, 10, 2);
	}

	/**
	 * Прямий запуск Gemini. Якщо не налаштовано — повертає ok=false.
	 *
	 * @return array{ok:bool, decision_hash?:string, explanation?:string, risk?:string}
	 */
	public function run_direct_gemini(array $payload): array {
		if (EmergencyStop::is_active()) {
			return ['ok' => false];
		}

		$default = ['ok' => false];

		// Дозволяємо провайдеру (або інтеграції) підставити реальний виклик.
		$result = apply_filters('seojusai/ai/run_gemini', $default, $payload);

		return is_array($result) ? $result : $default;
	}
}
