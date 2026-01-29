<?php
declare(strict_types=1);

namespace SEOJusAI\Modules;

use SEOJusAI\Core\Contracts\ModuleInterface;
use SEOJusAI\Core\Kernel;
use SEOJusAI\Background\Scheduler;
use SEOJusAI\Background\Watchdog;

defined('ABSPATH') || exit;

final class BackgroundModule implements ModuleInterface {

    public function get_slug(): string { return 'background'; }

    public function register(Kernel $kernel): void {
        $kernel->register_module($this->get_slug(), $this);
    }

    public function init(Kernel $kernel): void {
        add_action('init', function (): void {
            (new Scheduler())->register();
            (new Watchdog())->register();
        }, 25);
    }
}
