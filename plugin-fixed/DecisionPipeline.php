<?php
declare(strict_types=1);

namespace SEOJusAI\AI;

use SEOJusAI\Core\EmergencyStop;

defined('ABSPATH') || exit;

/**
 * DecisionPipeline
 *
 * Роль:
 * - трансформує результати Analyze у структуровані рішення
 * - НЕ виконує дії
 * - емитить рішення як сутність (DecisionResult)
 */
final class DecisionPipeline {

	private TaskGenerator $tasks;

	public function __construct() {
		$this->tasks = new TaskGenerator();
	}

	/**
	 * Запуск пайплайну рішень.
	 *
	 * @param array<string,mixed> $analysis
	 */
	public function run(array $analysis): array {

		if ( EmergencyStop::is_active() ) {
			return [];
		}

		$tasks = $this->tasks->generate($analysis);

		$out = [];

		foreach ($tasks as $task) {

			$decision = DecisionResult::fromTask($task);

			$out[] = [
				'type'       => $decision->type,
				'decision'   => $decision->decision,
				'post_id'    => $decision->post_id,
				'auto'       => $decision->auto,
				'source'     => $decision->source,
				'hash'       => $decision->hash,
				'created_at' => $decision->created_at,
			];

			/**
			 * ЄДИНА ТОЧКА ВИХОДУ РІШЕННЯ
			 *
			 * Далі рішення обробляється:
			 * - AutopilotEngine
			 * - Executors (через підтвердження)
			 * - Logging / Audit
			 */
			do_action('seojusai/decision/result', $decision);
		}

		return $out;
	}
}
