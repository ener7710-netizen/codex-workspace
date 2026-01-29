<?php
declare(strict_types=1);

namespace SEOJusAI\Tasks;

defined('ABSPATH') || exit;

add_filter('seojusai/tasks/execute', function($ok, string $action, array $payload, array $task){
    if ($action !== 'self_train_models') return $ok;
    $handler = new SelfTrainModelsTask();
    $handler($payload);
    return true;
}, 10, 4);