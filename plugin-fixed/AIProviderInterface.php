<?php
declare(strict_types=1);

namespace SEOJusAI\AI;

if ( ! defined('ABSPATH') ) {
	exit;
}

/**
 * AIProviderInterface
 *
 * ЖОРСТКИЙ контракт для всіх AI-провайдерів.
 *
 * Кожен провайдер:
 * - сам вирішує, чи він доступний
 * - сам формує prompt
 * - сам викликає API
 * - ПОВЕРТАЄ ТІЛЬКИ валідний JSON-контракт або null
 *
 * AIKernel не має жодної логіки if/else під конкретний AI.
 */
interface AIProviderInterface {

	/**
	 * Чи доступний провайдер для використання.
	 * (ключі, ліміти, режим, середовище)
	 */
	public function is_available(): bool;

	/**
	 * Основний метод аналізу.
	 *
	 * @param array  $context Контекст (page / site)
	 * @param string $scope   page | site
	 *
	 * @return array|null Строгий AI Decision Contract або null при помилці
	 */
	public function analyze(array $context, string $scope): ?array;

	/**
	 * Людинозрозуміла назва провайдера (для логів / UI).
	 */
	public function get_name(): string;

	/**
	 * Поточний режим роботи (free / paid / limited).
	 */
	public function get_mode(): string;
}
