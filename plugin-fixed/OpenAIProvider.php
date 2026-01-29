<?php
declare(strict_types=1);

namespace SEOJusAI\AI\Providers;

defined('ABSPATH') || exit;

use SEOJusAI\AI\AIProviderInterface;
use SEOJusAI\AI\Providers\OpenAIClient;
use SEOJusAI\Core\EmergencyStop;
use SEOJusAI\AI\Integrations\GeminiRuntimeBridge;
use SEOJusAI\AI\Integrations\GeminiAnalyticsGateway;
use SEOJusAI\Analytics\ObjectiveDatasetService;

final class OpenAIProvider implements AIProviderInterface {

	public function is_available(): bool {
		return !EmergencyStop::is_active();
	}

	public function get_name(): string {
		return 'OpenAI';
	}

	public function get_mode(): string {
		return 'paid';
	}

	public function analyze(array $context, string $scope): ?array {

		$api_key = apply_filters('seojusai/openai_key', '');
		$client  = new OpenAIClient($api_key);

		if (!$client->is_ready()) {
			return null;
		}

		// 1) Додаємо об'єктивні метрики (GSC/GA4) зі снапшотів у контекст.
		// Якщо контекст уже містить analytics (наприклад, PageActionPlanner), не перебудовуємо.
		if (!isset($context['analytics'])) {
			try {
				$context['analytics'] = (new ObjectiveDatasetService())->build(30);
			} catch (\Throwable $e) {
				// best-effort: не ламаємо стратегічний виклик
			}
		}

		// 2) Додаємо аналітичний контекст Gemini (як "джерело реальності" для Стратега).
		// 2a) Об'єктивний висновок Gemini по GA4+GSC (снапшоти).
		if (!isset($context['gemini_analytics'])) {
			try {
				$ga = GeminiAnalyticsGateway::get_or_compute(30, false);
				if (is_array($ga)) {
					$context['gemini_analytics'] = $ga;
				}
			} catch (\Throwable $e) {
				// best-effort
			}
		}

		// 2b) Загальний аналітик Gemini по всьому контексту (SERP/конкуренти).
		try {
			$gemini = GeminiRuntimeBridge::analyze_for_strategy($context);
			if (is_array($gemini)) {
				$context['gemini'] = $gemini;
			}
		} catch (\Throwable $e) {
			// best-effort: не ламаємо стратегічний виклик
		}

		$prompt = $this->build_prompt($context, $scope);

		$response = $client->generate($prompt, $context['mode'] ?? 'full');

		if (!$response) {
			return null;
		}

		// ❗ ЧИСТИМ markdown, якщо AI спробував
		$response = preg_replace('/^```json|```$/i', '', trim($response));

		$data = json_decode($response, true);

		return is_array($data) ? $data : null;
	}

	/**
	 * 🔒 ЖЁСТКИЙ PROMPT
	 */
	private function build_prompt(array $context, string $scope): string {

		// Scope-specific JSON contracts.
		if ($scope === 'page_actions') {
			$contract = <<<JSON
ПОВЕРНИ ВИКЛЮЧНО JSON.
НЕ ПИШИ ТЕКСТ.
НЕ ВИКОРИСТОВУЙ MARKDOWN.

ТИ ПРАЦЮЄШ У STRICT SOURCE MODE.
ЄДИНЕ ДЖЕРЕЛО ІСТИНИ — CONTEXT.
ЯКЩО ДАНИХ НЕМАЄ — НЕ ВИГАДУЙ.

СТРОГИЙ КОНТРАКТ:
{
  "meta": {
    "confidence": 0.0,
    "risk": "low | medium | high",
    "summary": "",
    "reasoning": ""
  },
  "actions": [
    {
      "type": "meta_title_update | meta_description_update | add_internal_link | add_section | add_schema | none",
      "reason": "",
      "confidence": 0.0,
      "auto_applicable": false,
      "value": ""
    }
  ]
}

ПРАВИЛА:
- Для meta_title_update/meta_description_update поле value ОБОВ'ЯЗКОВЕ (готовий текст).
- Для інших дій value може бути порожнім.
- Якщо нічого робити не треба — поверни один елемент з type = "none".
JSON;
		} else {
			$contract = <<<JSON
ПОВЕРНИ ВИКЛЮЧНО JSON.
НЕ ПИШИ ТЕКСТ.
НЕ ВИКОРИСТОВУЙ MARKDOWN.

СТРОГИЙ КОНТРАКТ:

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
		}

		$payload = json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

		return <<<PROMPT
SYSTEM:
Ти SEO AI для юридичного сайту (2026).
{$contract}

CONTEXT ({$scope}):
{$payload}

ПОВЕРНИ ЛИШЕ JSON.
PROMPT;
	}
}
