<?php
declare(strict_types=1);

namespace SEOJusAI\Autopilot;

defined('ABSPATH') || exit;

final class AutopilotLogger {

    private bool $registered = false;

    public function register(): void {
        if ($this->registered) return;
        $this->registered = true;

        add_action('seojusai/autopilot/log', [$this, 'handle'], 10, 1);
    }

    /** @param array<string,mixed> $payload */
    public function handle(array $payload): void {
        // minimal, safe journal (no secrets, no content)
        $repo = new AutopilotLogRepository();

        $event = isset($payload['event']) ? sanitize_key((string)$payload['event']) : 'unknown';
        $ts = isset($payload['timestamp']) ? (int)$payload['timestamp'] : time();
        $post_id = isset($payload['post_id']) ? (int)$payload['post_id'] : 0;

        $row = [
            'event' => $event,
            'timestamp' => $ts,
            'post_id' => $post_id,
            'type' => isset($payload['type']) ? sanitize_key((string)$payload['type']) : '',
            'hash' => isset($payload['hash']) ? sanitize_text_field((string)$payload['hash']) : '',
            'snapshot_id' => isset($payload['snapshot_id']) ? (int)$payload['snapshot_id'] : 0,
        ];

        $repo->append($row);
    }
}
