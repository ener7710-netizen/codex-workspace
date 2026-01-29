<?php
declare(strict_types=1);

namespace SEOJusAI\Strategy;

use DateTimeImmutable;

defined('ABSPATH') || exit;


/**
 * StrategicDecision is an immutable record that states a decision WAS made.
 * It does not encode execution, tasks, or side effects.
 */
final class StrategicDecision
{
    public function __construct(
        public readonly string $decision_id,
        public readonly string $decision_type,
        public readonly DateTimeImmutable $decided_at,
        public readonly string $context_hash
    ) {}
}
