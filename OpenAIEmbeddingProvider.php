<?php
declare(strict_types=1);

namespace SEOJusAI\Vectors;

use SEOJusAI\AI\Providers\OpenAIClient;

defined('ABSPATH') || exit;

final class OpenAIEmbeddingProvider implements EmbeddingProviderInterface {

    private OpenAIClient $client;
    private string $embedding_model;
    private int $dims;

    public function __construct(OpenAIClient $client, string $embedding_model = 'text-embedding-3-large', int $dims = 3072) {
        $this->client = $client;
        $this->embedding_model = $embedding_model;
        $this->dims = $dims;
    }

    public function is_ready(): bool { return $this->client->is_ready(); }
    public function model(): string { return $this->embedding_model; }
    public function dims(): int { return $this->dims; }

    public function embed(string $text): ?array {
        $text = trim(wp_strip_all_tags($text));
        if ($text === '' || !$this->is_ready()) return null;
        if (!method_exists($this->client, 'embed')) return null;
        return $this->client->embed($text, $this->embedding_model);
    }
}
