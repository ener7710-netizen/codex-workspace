<?php
declare(strict_types=1);

namespace SEOJusAI\Input;

use WP_Error;

defined('ABSPATH') || exit;

final class Validator {

    public static function require_keys(array $data, array $keys): ?WP_Error {
        foreach ($keys as $k) {
            if (!array_key_exists($k, $data)) {
                return new WP_Error('missing_field', 'Відсутнє поле: ' . sanitize_key((string)$k), ['status'=>400]);
            }
        }
        return null;
    }

    public static function max_items(array $arr, int $max, string $name='items'): ?WP_Error {
        if (count($arr) > $max) {
            return new WP_Error('too_many_items', 'Занадто багато елементів: ' . sanitize_text_field($name), ['status'=>400]);
        }
        return null;
    }
}
