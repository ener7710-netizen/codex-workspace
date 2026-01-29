<?php
declare(strict_types=1);

namespace SEOJusAI\Execution;

defined('ABSPATH') || exit;

use SEOJusAI\Execution\DTO\ExecutionIntentDTO;
use SEOJusAI\Execution\DTO\ExecutionResultDTO;
use SEOJusAI\Execution\Handlers\AnalysisExecutionHandler;

/**
 * ExecutionDispatcher
 *
 * Dispatches execution intents to handlers.
 *
 * @boundary Execution is controlled exclusively by AutopilotExecutionLoop.
 * UI, REST, and Admin layers must never trigger execution.
 * @boundary Must never throw fatals; must never execute non-allowed intents.
 */
final class ExecutionDispatcher
{
    public function dispatch(ExecutionIntentDTO $intent): ExecutionResultDTO
    {
        try {
            $type = strtoupper($intent->intentType());

            switch ($type) {
                case 'ANALYSIS':
                    $handler = new AnalysisExecutionHandler();
                    return $handler->handle($intent);

                default:
                    // Unsupported types are ignored safely.
                    return ExecutionResultDTO::fail('Непідтримуваний тип виконання.');
            }
        } catch (\Throwable $e) {
            return ExecutionResultDTO::fail('Помилка виконання: ' . $e->getMessage());
        }
    }
}
