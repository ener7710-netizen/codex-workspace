<?php
declare(strict_types=1);

namespace SEOJusAI\AI;

defined('ABSPATH') || exit;

use SEOJusAI\AI\Billing\CreditManager;
use SEOJusAI\AI\Providers\OpenAIProvider;
use SEOJusAI\AI\Providers\GeminiProvider;

final class AIProviderManager {

	/** @var AIProviderInterface[] */
	private array $providers = [];

	public function __construct() {

		$this->providers = [
			new OpenAIProvider(),
			new GeminiProvider(),
		];
	}

	public function analyze(array $context, string $scope): ?array {

		if (!CreditManager::has_credits(1)) {
			return null;
		}

		foreach ($this->providers as $provider) {

			if (!$provider->is_available()) {
				continue;
			}

			if (!CreditManager::consume(1)) {
				return null;
			}

			$result = $provider->analyze($context, $scope);

			if (!is_array($result)) {
				CreditManager::add(1); // rollback
				continue;
			}

			return $result;
		}

		return null;
	}
}
