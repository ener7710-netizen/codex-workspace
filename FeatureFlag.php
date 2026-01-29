<?php
declare(strict_types=1);

namespace SEOJusAI\Features;

defined('ABSPATH') || exit;

final class FeatureFlag {

    public string $key;
    public string $title;
    public string $description;
    public string $stability; // stable|experimental
    public bool $default;
    public string $since;

    public function __construct(string $key, string $title, string $description, string $stability, bool $default, string $since) {
        $this->key = $key;
        $this->title = $title;
        $this->description = $description;
        $this->stability = $stability;
        $this->default = $default;
        $this->since = $since;
    }
}
