<?php
declare(strict_types=1);

namespace SEOJusAI\Autopilot;

defined('ABSPATH') || exit;

use SEOJusAI\Execution\ExecutionIntentRepository;
use SEOJusAI\Execution\ExecutionDispatcher;

/**
 * AutopilotExecutionService
 *
 * Executes a claimed intent (running + claimed_by current worker) via dispatcher.
 * @boundary This service must not be auto-triggered by UI. Used by loop only.
 */
final class AutopilotExecutionService
{
    private ExecutionIntentRepository $repo;
    private ExecutionDispatcher $dispatcher;

    public function __construct(?ExecutionIntentRepository $repo = null, ?ExecutionDispatcher $dispatcher = null)
    {
        $this->repo = $repo ?: new ExecutionIntentRepository();
        $this->dispatcher = $dispatcher ?: new ExecutionDispatcher();
    }

    public function executeClaimedIntent(int $intentId): bool
    {
        $workerId = AutopilotWorkerIdentity::id();

        $intent = $this->repo->findById($intentId);
        if (!$intent) {
            return false;
        }

        // Strict lifecycle gates
        if ($intent->status() !== 'running') {
            return false;
        }
        if ($intent->claimedBy() !== $workerId) {
            return false;
        }

        $result = $this->dispatcher->dispatch($intent);

        if ($result->success()) {
            return $this->repo->markCompleted($intentId, $workerId);
        }

        return $this->repo->markFailed($intentId, $workerId, $result->message());
    }
}
