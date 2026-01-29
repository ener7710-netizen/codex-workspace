<?php
declare(strict_types=1);

namespace SEOJusAI\AI;

use SEOJusAI\Security\SecretsVault;

defined('ABSPATH') || exit;

final class OpenAIKey {

    public static function get(): string {

        // 1️⃣ Vault (recommended)
        $vault = new SecretsVault();
        $key = $vault->get('openai_key');

        // 2️⃣ Legacy option (backward compat)
        if ($key === '') {
            $key = (string) get_option('seojusai_openai_key', '');
        }

        // 3️⃣ wp-config fallback
        if ($key === '' && defined('SEOJUSAI_OPENAI_KEY')) {
            $key = (string) SEOJUSAI_OPENAI_KEY;
        }

        // 4️⃣ Filter
        return (string) apply_filters('seojusai/openai_key', $key);
    }
}
