<?php
declare(strict_types=1);

namespace SEOJusAI\AI\Providers;

defined('ABSPATH') || exit;

use SEOJusAI\AI\AIProviderInterface;
use SEOJusAI\AI\Providers\GeminiClient;
use SEOJusAI\Core\EmergencyStop;

final class GeminiProvider implements AIProviderInterface {

	public function is_available(): bool {
		return !EmergencyStop::is_active();
	}

	public function get_name(): string {
		return 'Gemini';
	}

	public function get_mode(): string {
		return 'paid';
	}

	public function analyze(array $context, string $scope): ?array {

		$api_key = apply_filters('seojusai/gemini_key', '');
		$client  = new GeminiClient($api_key);

		if (!$client->is_ready()) {
			return null;
		}

		$prompt = $this->build_prompt($context, $scope);

		$response = $client->generate($prompt, $context['mode'] ?? 'full');

		if (!$response) {
			return null;
		}

		$response = preg_replace('/^```json|```$/i', '', trim($response));
		$data = json_decode($response, true);

		return is_array($data) ? $data : null;
	}

	private function build_prompt(array $context, string $scope): string {

		$contract = <<<JSON
ПОВЕРНИ ТІЛЬКИ JSON.
ЖОДНОГО ТЕКСТУ.

{
  "meta": {
    "confidence": 0.0,
    "risk": "low | medium | high",
    "summary": "",
    "reasoning": ""
  },
  "actions": [
    {
      "action": "",
      "auto": false
    }
  ]
}
JSON;

		$payload = json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

		return <<<PROMPT
SYSTEM:
Ти AI-аналітик Google (SEO, юридичні сайти).
{$contract}

CONTEXT ({$scope}):
{$payload}

ПОВЕРНИ JSON.
PROMPT;
	}
}
