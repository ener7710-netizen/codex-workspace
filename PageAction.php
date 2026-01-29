<?php
declare(strict_types=1);

namespace SEOJusAI\PageActions;

defined('ABSPATH') || exit;

/**
 * PageAction
 *
 * Domain DTO: структурований опис дії, запропонованої AI для конкретної сторінки.
 * Не виконує дію та не змінює стан.
 */
final class PageAction {

    public string $type;
    public string $reason;
    public float $confidence;
    public bool $auto_applicable;
    /**
     * Optional executable value (e.g., proposed meta title/description text).
     * Kept empty when the action is informational only.
     */
    public string $value;
    public string $source;

    public function __construct(
        string $type,
        string $reason,
        float $confidence = 0.5,
        bool $auto_applicable = false,
        string $value = '',
        string $source = 'ai'
    ) {
        $this->type = trim($type) !== '' ? trim($type) : 'unknown';
        $this->reason = trim($reason);
        $this->confidence = self::clamp_confidence($confidence);
        $this->auto_applicable = (bool) $auto_applicable;
        $this->value = (string) $value;
        $this->source = trim($source) !== '' ? trim($source) : 'ai';
    }

    /**
     * @param array<string,mixed> $a
     */
    public static function from_array(array $a, string $sourceFallback = 'ai'): self {
        $type = isset($a['type']) ? (string) $a['type'] : (isset($a['action']) ? (string) $a['action'] : 'unknown');
        $reason = isset($a['reason']) ? (string) $a['reason'] : '';
        $conf = isset($a['confidence']) && is_numeric($a['confidence']) ? (float) $a['confidence'] : 0.5;
        $auto = isset($a['auto']) ? (bool) $a['auto'] : (isset($a['auto_applicable']) ? (bool) $a['auto_applicable'] : false);
        $value = isset($a['value']) ? (string) $a['value'] : '';
        $src = isset($a['source']) ? (string) $a['source'] : $sourceFallback;
        return new self($type, $reason, $conf, $auto, $value, $src);
    }

    /**
     * @return array<string,mixed>
     */
    public function to_array(): array {
        return [
            'type' => $this->type,
            'reason' => $this->reason,
            'confidence' => $this->confidence,
            'auto_applicable' => $this->auto_applicable,
            'value' => $this->value,
            'source' => $this->source,
        ];
    }

    private static function clamp_confidence(float $c): float {
        if ($c < 0.0) {
            return 0.0;
        }
        if ($c > 1.0) {
            return 1.0;
        }
        return $c;
    }
}
