<?php
declare(strict_types=1);

namespace SEOJusAI\Autopilot;

defined('ABSPATH') || exit;

use SEOJusAI\Execution\ExecutionIntentRepository;

/**
 * AutopilotExecutionLoop
 *
 * Processes ONE pending ANALYSIS execution intent per run.
 * @boundary Autonomous behavior is gated by explicit governance flags.
 */
final class AutopilotExecutionLoop
{
    private ExecutionIntentRepository $repo;
    private AutopilotExecutionService $executor;

    public function __construct(?ExecutionIntentRepository $repo = null, ?AutopilotExecutionService $executor = null)
    {
        $this->repo = $repo ?: new ExecutionIntentRepository();
        $this->executor = $executor ?: new AutopilotExecutionService($this->repo);
    }

    public function run(): void
    {
        // Governance: execution must be explicitly enabled.
        $executionEnabled = (bool) get_option('seojusai_execution_enabled', false);
        if ($executionEnabled !== true) {
            return;
        }

        // Read-only check (future use).
        $learningEnabled = (bool) get_option('seojusai_learning_enabled', false);
        unset($learningEnabled);

        $workerId = AutopilotWorkerIdentity::id();

        // Claim ONE pending ANALYSIS intent atomically.
        $claimed = $this->repo->claimNextPending($workerId, 'analysis');
        if (!$claimed) {
            return;
        }

        // Minimal logging (safe).
        if (function_exists('do_action')) {
            do_action('seojusai/autopilot/log', [
                'event' => 'autopilot_loop_claimed',
                'timestamp' => time(),
                'intent_id' => $claimed->id(),
                'intent_type' => $claimed->intentType(),
            ]);
        }

        // Execute read-only analysis and close status.
        $ok = $this->executor->executeClaimedIntent($claimed->id());

        if (function_exists('do_action')) {
            do_action('seojusai/autopilot/log', [
                'event' => $ok ? 'autopilot_loop_completed' : 'autopilot_loop_failed',
                'timestamp' => time(),
                'intent_id' => $claimed->id(),
                'intent_type' => $claimed->intentType(),
            ]);
        }
    }
}
