<?php
declare(strict_types=1);

namespace SEOJusAI\Rest\Contracts;

defined('ABSPATH') || exit;


/**
 * Interface RestControllerInterface
 *
 * Єдиний контракт для всіх REST-контролерів SEOJusAI.
 *
 * ВАЖЛИВО:
 * - Контролер НЕ повинен містити бізнес-логіку
 * - Контролер НЕ повинен вирішувати питання доступу (ACL)
 * - Контролер ЛИШЕ реєструє маршрути і приймає запити
 */
interface RestControllerInterface {

	/**
	 * Реєстрація REST-маршрутів.
	 */
	public function register_routes(): void;
}
