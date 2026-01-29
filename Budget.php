<?php
declare(strict_types=1);

namespace SEOJusAI\Budget;

if ( ! defined('ABSPATH') ) {
	exit;
}

/**
 * Budget
 *
 * Контроль добових лімітів AI-запитів.
 * Режими:
 * - editor (live)
 * - full (audit / site)
 */
final class Budget {

	private const OPTION_KEY = 'seojusai_budget';
	private const TRANSIENT_PREFIX = 'seojusai_budget_used_';

	/**
	 * Дефолтні ліміти (на добу).
	 */
	private array $defaults = [
		'editor' => 50,
		'full'   => 20,
	];

	/**
	 * Реєстрація подій.
	 */
	public function register(): void {

		// Облік використання
		add_action('seojusai/ai/decision', function (array $payload) {
			$this->consume_from_decision($payload);
		}, 10, 1);
	}

	/**
	 * Чи дозволено запуск AI зараз.
	 */
	public function can_run(string $mode): bool {

		$limits = $this->get_limits();
		$used   = $this->get_used($mode);

		$limit = (int) ($limits[$mode] ?? 0);

		if ( $limit <= 0 ) {
			return false;
		}

		return $used < $limit;
	}

	/**
	 * Списати використання після рішення AI.
	 */
	private function consume_from_decision(array $payload): void {

		$type = $payload['type'] ?? '';

		// page → editor, site → full
		$mode = ($type === 'page') ? 'editor' : 'full';

		$key = $this->get_transient_key($mode);

		$used = (int) get_transient($key);
		$used++;

		// Зберігаємо до кінця дня
		set_transient($key, $used, $this->seconds_until_end_of_day());
	}

	/**
	 * Отримати поточні ліміти.
	 */
	public function get_limits(): array {

		$stored = get_option(self::OPTION_KEY, []);

		return array_merge($this->defaults, is_array($stored) ? $stored : []);
	}

	/**
	 * Отримати використання за сьогодні.
	 */
	public function get_used(string $mode): int {

		return (int) get_transient($this->get_transient_key($mode));
	}

	/**
	 * Отримати залишок.
	 */
	public function get_remaining(string $mode): int {

		$limits = $this->get_limits();
		$limit  = (int) ($limits[$mode] ?? 0);
		$used   = $this->get_used($mode);

		return max(0, $limit - $used);
	}

	/**
	 * Оновлення лімітів (через REST/UI).
	 */
	public function update_limits(array $limits): void {

		$clean = [];

		foreach ( $this->defaults as $mode => $default ) {
			$clean[$mode] = max(0, (int) ($limits[$mode] ?? $default));
		}

		update_option(self::OPTION_KEY, $clean, false);
	}

	/**
	 * Ключ transient для режиму.
	 */
	private function get_transient_key(string $mode): string {
		return self::TRANSIENT_PREFIX . sanitize_key($mode) . '_' . gmdate('Ymd');
	}

	/**
	 * Секунди до кінця поточної доби (UTC-safe).
	 */
	private function seconds_until_end_of_day(): int {

		$now = time();
		$end = strtotime('tomorrow 00:00:00', $now);

		return max(60, $end - $now);
	}
}
