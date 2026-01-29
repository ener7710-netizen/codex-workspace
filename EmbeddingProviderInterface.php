<?php
declare(strict_types=1);

namespace SEOJusAI\Vectors;

defined('ABSPATH') || exit;

interface EmbeddingProviderInterface {
    public function is_ready(): bool;
    /** @return array<float>|null */
    public function embed(string $text): ?array;
    public function model(): string;
    public function dims(): int;
}
