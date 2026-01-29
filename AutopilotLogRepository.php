<?php
declare(strict_types=1);

namespace SEOJusAI\Autopilot;

defined('ABSPATH') || exit;

final class AutopilotLogRepository {

    private const OPTION_KEY = 'seojusai_autopilot_journal';
    private const MAX_ITEMS = 200;

    /** @return array<int,array<string,mixed>> */
    public function list_recent(int $limit = 50): array {
        $limit = max(1, min(200, $limit));
        $items = get_option(self::OPTION_KEY, []);
        if (!is_array($items)) return [];

        $items = array_values(array_filter($items, static fn($x) => is_array($x)));
        $items = array_reverse($items); // newest first

        return array_slice($items, 0, $limit);
    }

    /** @param array<string,mixed> $row */
    public function append(array $row): void {
        $items = get_option(self::OPTION_KEY, []);
        if (!is_array($items)) $items = [];

        $row['event'] = sanitize_key((string)($row['event'] ?? 'unknown'));
        $row['timestamp'] = isset($row['timestamp']) ? (int)$row['timestamp'] : time();
        $row['post_id'] = isset($row['post_id']) ? (int)$row['post_id'] : 0;

        $items[] = $row;

        if (count($items) > self::MAX_ITEMS) {
            $items = array_slice($items, -self::MAX_ITEMS);
        }

        update_option(self::OPTION_KEY, $items, false);
    }
}
