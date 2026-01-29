<?php
declare(strict_types=1);

namespace SEOJusAI\Crawl;

defined('ABSPATH') || exit;

/**
 * HtmlSnapshotValue
 * Immutable value-object for a captured HTML snapshot.
 */
final class HtmlSnapshotValue {

    private string $url;
    private string $html;
    private int $captured_at;

    public function __construct(string $url, string $html, int $captured_at) {
        $this->url = $url;
        $this->html = $html;
        $this->captured_at = $captured_at > 0 ? $captured_at : time();
    }

    public function get_url(): string {
        return $this->url;
    }

    public function get_html(): string {
        return $this->html;
    }

    public function get_captured_at(): int {
        return $this->captured_at;
    }
}
