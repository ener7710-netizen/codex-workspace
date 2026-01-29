<?php
declare(strict_types=1);

namespace SEOJusAI\CLI;

defined('ABSPATH') || exit;

final class CommandKernel {

    public static function register(): void {
        if (!defined('WP_CLI') || !WP_CLI) {
            return;
        }
        if (!class_exists('\WP_CLI')) {
            return;
        }

        \WP_CLI::add_command('seojusai health', [HealthCommand::class, 'run']);
        \WP_CLI::add_command('seojusai queue', [QueueCommand::class, 'run']);
        \WP_CLI::add_command('seojusai doctor', [DoctorCommand::class, 'run']);
    }
}
