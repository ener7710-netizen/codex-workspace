<?php
declare(strict_types=1);

namespace SEOJusAI\Modules;

use SEOJusAI\Core\Contracts\ModuleInterface;
use SEOJusAI\Core\Kernel;
use SEOJusAI\Tasks\TaskQueue;

defined('ABSPATH') || exit;

final class LearningModule implements ModuleInterface {

    public function get_slug(): string { return 'learning'; }

    public function register(Kernel $kernel): void {
        $kernel->register_module($this->get_slug(), $this);
    }

    public function init(Kernel $kernel): void {
        add_action('rest_api_init', function (): void {
            register_rest_route('seojusai/v1', '/learning/run', [
                'methods'  => 'POST',
                'permission_callback' => ['SEOJusAI\\Rest\\RestKernel', 'can_manage_static'],
                'callback' => function () {
                    $q = new TaskQueue();
					$task_id = $q->enqueue('learning/run_weekly', [
						'priority' => 'low',
						'max_attempts' => 1,
						'source' => 'user',
					], 'learning:run_weekly');
					return rest_ensure_response(['ok' => (bool) $task_id, 'task_id' => (int) $task_id]);
                }
            ]);
        }, 20);
    }
}
