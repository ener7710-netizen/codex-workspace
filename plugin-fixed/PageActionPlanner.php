<?php
declare(strict_types=1);

namespace SEOJusAI\AI\PageActions;

defined('ABSPATH') || exit;

use SEOJusAI\Analytics\ObjectiveDatasetService;
use SEOJusAI\AI\Integrations\GeminiAnalyticsGateway;
use SEOJusAI\AI\Providers\OpenAIProvider;
use SEOJusAI\PageActions\PageAction;
use SEOJusAI\PageActions\PageInsightService;

/**
 * PageActionPlanner
 *
 * Шар: AI Orchestration.
 *
 * Відповідальність:
 * - зібрати PageInsight (факти)
 * - додати objective analytics dataset (снапшоти)
 * - отримати висновок Gemini (об'єктивний)
 * - отримати план дій від OpenAI (без виконання)
 *
 * Інваріанти:
 * - не виконує Autopilot/apply
 * - не робить live Google API запитів (все через снапшоти/датасет)
 */
final class PageActionPlanner {

    private PageInsightService $insights;
    private ObjectiveDatasetService $dataset;

    public function __construct(?PageInsightService $insights = null, ?ObjectiveDatasetService $dataset = null) {
        $this->insights = $insights instanceof PageInsightService ? $insights : new PageInsightService();
        $this->dataset  = $dataset instanceof ObjectiveDatasetService ? $dataset : new ObjectiveDatasetService();
    }

    /**
     * @return array<string,mixed>
     */
    public function plan(string $urlOrPath, int $topDatasetRows = 50): array {
        $topDatasetRows = max(5, min(200, $topDatasetRows));

        $page = $this->insights->build($urlOrPath);
        $analytics = $this->dataset->build($topDatasetRows);

        // Об'єктивний висновок Gemini по GA4+GSC (снапшоти).
        $geminiAnalytics = null;
        try {
            $geminiAnalytics = GeminiAnalyticsGateway::get_or_compute(min(50, $topDatasetRows), false);
        } catch (\Throwable $e) {
            $geminiAnalytics = null;
        }

        // OpenAI план дій (тільки планування).
        $openai = new OpenAIProvider();
        $plan = null;
        try {
            $ctx = [
                'mode' => 'full',
                'scope' => 'page_actions',
                'page' => $page,
                // Важливо: прокидуємо вже зібраний dataset, щоб провайдер не будував його повторно.
                'analytics' => $analytics,
                // Додаємо об'єктивний висновок Gemini, якщо є.
                'gemini_analytics' => $geminiAnalytics,
            ];
            $plan = $openai->analyze($ctx, 'page_actions');
        } catch (\Throwable $e) {
            $plan = null;
        }

        // Нормалізуємо дії до PageAction[].
        $actions = [];
        if (is_array($plan) && isset($plan['actions']) && is_array($plan['actions'])) {
            foreach ($plan['actions'] as $a) {
                if (!is_array($a)) {
                    continue;
                }
                // Підтримка двох форматів: {action, auto} або {type, reason, ...}
                if (!isset($a['type']) && isset($a['action'])) {
                    $a['type'] = (string) $a['action'];
                }
                $actions[] = PageAction::from_array($a, 'openai');
            }
        }

        return [
            'ok' => true,
            'generated_at' => gmdate('c'),
            'page' => $page,
            'gemini_analytics' => $geminiAnalytics,
            'plan' => $plan,
            'actions' => array_map(static fn(PageAction $pa) => $pa->to_array(), $actions),
        ];
    }
}
