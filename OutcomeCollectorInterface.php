<?php
declare(strict_types=1);

namespace SEOJusAI\Learning;

defined('ABSPATH') || exit;

interface OutcomeCollectorInterface {

    /** @return array<string,mixed> */
    public function before(string $entity_type, int $entity_id): array;

    /** @return array<string,mixed> */
    public function after(string $entity_type, int $entity_id): array;

    /** @return array<string,mixed> */
    public function diff(array $before, array $after): array;
}
