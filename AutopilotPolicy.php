<?php
declare(strict_types=1);

namespace SEOJusAI\Autopilot;

use SEOJusAI\AI\DecisionContract;

defined('ABSPATH') || exit;

/**
 * AutopilotPolicy
 * Єдина матриця дозволів для Full Autopilot SAFE Mode.
 *
 * ✅ Дозволяє лише low-risk, allowlisted дії, без зміни фактів/норм/дат.
 * ✅ Ліміти на кількість дій та розмір секцій.
 */
final class AutopilotPolicy {

    /** @var string[] */
    private array $allow_actions = [
        'add_section',
        'add_internal_link',
        'add_faq_schema',
        'add_contact_schema',
    ];
    /** @return string[] */
    public function get_allow_actions(): array {
        return $this->allow_actions;
    }



    public function can_auto_apply(array $decision, array $ctx = []): bool {
        if (!DecisionContract::validate($decision)) {
            return false;
        }

        $post_id = (int)($ctx['post_id'] ?? 0);
        if ($post_id <= 0) return false;

        $meta = is_array($decision['meta'] ?? null) ? (array)$decision['meta'] : [];
        $risk = sanitize_key((string)($meta['risk'] ?? ''));
        $confidence = isset($meta['confidence']) ? (float)$meta['confidence'] : 0.0;

        // only low risk, confidence gate
        if ($risk !== 'low') return false;
        if ($confidence < 0.55) return false;

        $actions = is_array($decision['actions'] ?? null) ? (array)$decision['actions'] : [];
        if (!$actions) return false;

        // hard caps
        if (count($actions) > 6) return false;

        foreach ($actions as $a) {
            if (!is_array($a)) return false;
            $type = sanitize_key((string)($a['action'] ?? ''));
            if (!in_array($type, $this->allow_actions, true)) return false;

            // size limits for add_section to avoid huge rewrites
            if ($type === 'add_section') {
                $content = (string)($a['content'] ?? '');
                if (strlen($content) > 8000) return false; // bytes cap
            }
        }

        // avoid autos on protected/critical pages (home, front)
        if ($post_id > 0) {
            $front = (int) get_option('page_on_front', 0);
            $posts = (int) get_option('page_for_posts', 0);
            if ($post_id === $front || $post_id === $posts) return false;
        }

        return true;
    }
}
