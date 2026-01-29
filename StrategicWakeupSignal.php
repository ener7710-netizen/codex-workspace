<?php
declare(strict_types=1);

namespace SEOJusAI\Strategy;

use DateTimeImmutable;

defined('ABSPATH') || exit;


/**
 * StrategicWakeupSignal represents that conditions MAY have changed.
 * It contains no evaluation or triggering logic.
 */
final class StrategicWakeupSignal
{
    public function __construct(
        public readonly string $signal_type,
        public readonly DateTimeImmutable $observed_at
    ) {}
}
