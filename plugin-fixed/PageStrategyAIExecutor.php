<?php
declare(strict_types=1);

namespace SEOJusAI\AI;

defined('ABSPATH') || exit;

final class PageStrategyAIExecutor
{
    /**
     * Запустити AI-стратегію для сторінки
     *
     * @param array $payload Готовий STRICT payload
     * @return array{
     *   score:int,
     *   analysis:array,
     *   tasks:array
     * }
     */
    public static function run(array $payload): array
    {
        $api_key = get_option('seojusai_openai_key');
        if (!$api_key) {
            return self::fallback('OpenAI API key відсутній');
        }

        $model = get_option('seojusai_openai_model', 'gpt-4o');

        $system_prompt = self::build_system_prompt($payload);

        $response = wp_remote_post(
            'https://api.openai.com/v1/chat/completions',
            [
                'headers' => [
                    'Authorization' => 'Bearer ' . $api_key,
                    'Content-Type'  => 'application/json',
                ],
                'timeout' => 60,
                'body' => wp_json_encode([
                    'model' => $model,
                    'messages' => [
                        [
                            'role'    => 'system',
                            'content' => $system_prompt,
                        ],
                    ],
                    'response_format' => [
                        'type' => 'json_object',
                    ],
                ], JSON_UNESCAPED_UNICODE),
            ]
        );

        if (is_wp_error($response)) {
            return self::fallback($response->get_error_message());
        }

        $code = (int) wp_remote_retrieve_response_code($response);
        if ($code < 200 || $code >= 300) {
            return self::fallback('HTTP ' . $code);
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);
        $content = $body['choices'][0]['message']['content'] ?? '';

        $decoded = json_decode($content, true);
        if (!is_array($decoded)) {
            return self::fallback('AI повернув не JSON');
        }

        return [
            'score'    => (int) ($decoded['score'] ?? 0),
            'analysis' => self::normalize_analysis($decoded['analysis'] ?? []),
            'tasks'    => self::normalize_tasks($decoded['tasks'] ?? []),
        ];
    }

    /**
     * Системний промпт (STRICT SOURCE MODE)
     */
    private static function build_system_prompt(array $payload): string
    {
        return <<<PROMPT
ТИ ПРАЦЮЄШ У STRICT SOURCE MODE.

ЄДИНЕ ДЖЕРЕЛО ІСТИНИ — PAYLOAD НИЖЧЕ.
ЗАБОРОНЕНО:
- вигадувати факти
- аналізувати HTML
- використовувати знання поза payload
- робити припущення

ЯКЩО ЧОГОСЬ НЕМАЄ В payload — ПИШИ:
"❌ Дані відсутні у джерелі"

ВСІ ПРОПОЗИЦІЇ ФОРМУЛЮЙ ЯК:
"РЕКОМЕНДАЦІЯ: ..."

ТВОЄ ЗАВДАННЯ:
1. Оціни ризики для SEO / YMYL
2. Поясни, ЧОМУ сторінка не досягає топу
3. Сформуй конкретні рекомендації

ПОВЕРНИ СТРОГО JSON:
{
  "score": 0-100,
  "analysis": [
    { "label": "", "status": "good|warning|bad", "desc": "" }
  ],
  "tasks": [
    {
      "action": "РЕКОМЕНДАЦІЯ: ...",
      "type": "content|technical|schema|new_page",
      "priority": "high|medium|low",
      "auto": false
    }
  ]
}

--- PAYLOAD ---
{$this->json($payload)}
--- КІНЕЦЬ PAYLOAD ---
PROMPT;
    }

    private static function json(array $data): string
    {
        return wp_json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    }

    private static function normalize_analysis(array $analysis): array
    {
        $out = [];
        foreach ($analysis as $a) {
            if (empty($a['label']) || empty($a['desc'])) {
                continue;
            }
            $out[] = [
                'label'  => (string) $a['label'],
                'status' => in_array($a['status'] ?? '', ['good','warning','bad'], true)
                    ? $a['status']
                    : 'warning',
                'desc'   => (string) $a['desc'],
            ];
        }
        return $out;
    }

    private static function normalize_tasks(array $tasks): array
    {
        $out = [];
        foreach ($tasks as $t) {
            if (empty($t['action'])) {
                continue;
            }
            $out[] = [
                'action'   => (string) $t['action'],
                'type'     => in_array($t['type'] ?? '', ['content','technical','schema','new_page'], true)
                    ? $t['type']
                    : 'content',
                'priority' => in_array($t['priority'] ?? '', ['high','medium','low'], true)
                    ? $t['priority']
                    : 'medium',
                'auto'     => false,
            ];
        }
        return $out;
    }

    private static function fallback(string $reason): array
    {
        return [
            'score'    => 0,
            'analysis' => [
                [
                    'label'  => 'ai_error',
                    'status' => 'bad',
                    'desc'   => 'AI недоступний: ' . $reason,
                ],
            ],
            'tasks'    => [],
        ];
    }
}
