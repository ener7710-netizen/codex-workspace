<?php
declare(strict_types=1);

namespace SEOJusAI\Modules;

use SEOJusAI\Core\Contracts\ModuleInterface;
use SEOJusAI\Core\Kernel;
use SEOJusAI\CaseLearning\CaseLearningService;

defined('ABSPATH') || exit;

/**
 * CaseLearningModule (v1)
 * Внутрішня база кейсів для self-learning. Без персональних даних.
 */
final class CaseLearningModule implements ModuleInterface {

    public function get_slug(): string { return 'case_learning'; }

    public function register(Kernel $kernel): void {
        $kernel->register_module($this->get_slug(), $this);
    }

    public function init(Kernel $kernel): void {
        CaseLearningService::register();
    }
}
