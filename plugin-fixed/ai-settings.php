<?php
declare(strict_types=1);

namespace SEOJusAI\Admin;

use SEOJusAI\GSC\GscServiceAccount;
use SEOJusAI\GA4\Ga4ServiceAccount;
use SEOJusAI\AI\Billing\CreditManager; // Импорт для баланса
use SEOJusAI\Features\FeatureRegistry;
use SEOJusAI\Features\FeatureResolver;
use SEOJusAI\Autopilot\AutopilotReliability;
use SEOJusAI\Input\Input;

defined('ABSPATH') || exit;

if (!current_user_can('manage_options')) return;

$tab = sanitize_key((string) (Input::get('tab', 'ai')));
$base_url = admin_url('admin.php?page=seojusai-ai');

// Обробка збереження форм
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($tab === 'ai' && (Input::post('seojusai_save_ai', null) !== null)) {
        check_admin_referer('seojusai_ai_action');
        update_option('seojusai_openai_key', sanitize_text_field(Input::post('openai_key') ?? ''));
        update_option('seojusai_openai_model', sanitize_text_field(Input::post('openai_model') ?? 'gpt-4o-mini'));
        update_option('seojusai_gemini_key', sanitize_text_field(Input::post('gemini_key') ?? ''));
        echo '<div class="notice notice-success is-dismissible"><p>Налаштування AI оновлено.</p></div>';
    }

    if ($tab === 'data' && (Input::post('seojusai_save_data', null) !== null)) {
        check_admin_referer('seojusai_data_action');
        update_option('seojusai_pagespeed_key', sanitize_text_field(Input::post('pagespeed_key') ?? ''));
        update_option('seojusai_serp_key', sanitize_text_field(Input::post('serp_key') ?? ''));
		// GA4 settings (Service Account)
		update_option('seojusai_ga4_property_id', sanitize_text_field(Input::post('ga4_property_id') ?? ''));
		// GSC property (site) override
		update_option('seojusai_gsc_site', sanitize_text_field(Input::post('gsc_site') ?? ''));
        echo '<div class="notice notice-success is-dismissible"><p>Ключі API оновлено.</p></div>';
    }
}
if ($tab === 'autopilot' && (Input::post('seojusai_save_autopilot_ui', null) !== null)) {
    check_admin_referer('seojusai_autopilot_ui_action');

    // ✅ Manual pause / resume
    if (class_exists(AutopilotReliability::class)) {
        if (!empty(Input::post('seojusai_autopilot_pause'))) {
            AutopilotReliability::pause('manual', ['by' => get_current_user_id()]);
            echo '<div class="notice notice-warning is-dismissible"><p>' . esc_html__('Автопілот поставлено на паузу.', 'seojusai') . '</p></div>';
        } elseif (!empty(Input::post('seojusai_autopilot_resume'))) {
            AutopilotReliability::resume('manual');
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Автопілот відновлено.', 'seojusai') . '</p></div>';
        }

        // ✅ Reliability thresholds
        $min_conf = (Input::post('reliability_min_confidence', null) !== null) ? (float) Input::post('reliability_min_confidence') : 0.70;
        $max_fail = (Input::post('reliability_max_fail_rate', null) !== null) ? (float) Input::post('reliability_max_fail_rate') : 0.25;
        $min_samp = (Input::post('reliability_min_sample', null) !== null) ? (int) Input::post('reliability_min_sample') : 10;
        AutopilotReliability::set_thresholds($min_conf, $max_fail, $min_samp);
    }

    // Feature flag gate
    $full_safe = !empty(Input::post('autopilot_full_safe_mode_v1'));
    if (class_exists(FeatureResolver::class)) {
        FeatureResolver::set('autopilot_full_safe_mode_v1', $full_safe, get_current_user_id(), 'admin_ui');
    }

    // Mode + allow_apply options
    $mode = (Input::post('autopilot_mode', null) !== null) ? sanitize_key((string)Input::post('autopilot_mode')) : 'shadow';
    if (!in_array($mode, ['shadow','limited','full'], true)) $mode = 'shadow';

    $allow_apply = !empty(Input::post('autopilot_allow_apply')) ? 1 : 0;

    $opt = get_option('seojusai_autopilot', []);
    if (!is_array($opt)) $opt = [];
    $opt['mode'] = $mode;
    $opt['allow_apply'] = $allow_apply;
    update_option('seojusai_autopilot', $opt, false);

    echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Налаштування автопілота збережено.', 'seojusai') . '</p></div>';
}



if ($tab === 'features' && (Input::post('seojusai_save_features', null) !== null)) {
    check_admin_referer('seojusai_features_action');
    if (class_exists('SEOJusAI\\Features\\FeatureResolver')) {
        FeatureResolver::ensure_defaults();
        $values = FeatureResolver::get_all();
        $posted = (Input::post('flags', null) !== null) && is_array(Input::post('flags')) ? Input::post('flags') : [];
        foreach (FeatureRegistry::all() as $flag) {
            $enabled = isset($posted[$flag->key]) ? true : false;
            // За замовчуванням не даємо випадково вмикати experimental без явного чекбоксу
            FeatureResolver::set($flag->key, $enabled, get_current_user_id(), 'admin_ui');
        }
        echo '<div class="notice notice-success is-dismissible"><p>Feature Flags оновлено.</p></div>';
    }
}

// Перевірка Google credentials (єдиний ключ для GSC + GA4)
$uploads = wp_upload_dir();
$uploads_basedir = rtrim((string) ($uploads['basedir'] ?? ''), '/');
$uploads_baseurl  = rtrim((string) ($uploads['baseurl'] ?? ''), '/');

$key_rel = 'seojusai/keys/gsc-service-account.json';

$key_json_path = ($uploads_basedir !== '' ? $uploads_basedir . '/' : WP_CONTENT_DIR . '/uploads/') . $key_rel;
$key_public_path = ($uploads_baseurl !== '' ? $uploads_baseurl . '/' : (content_url('uploads/') . '/')) . $key_rel;

// --- ЄДИНИЙ СТАТУС ДЛЯ GOOGLE KEY (GSC + GA4) ---
$key_ready = false;
$key_msg = 'Файл JSON не знайдено за шляхом: ' . esc_html(parse_url($key_public_path, PHP_URL_PATH) ?: $key_public_path);

if (is_readable($key_json_path)) {
    try {
        // Перевіряємо валідність через GSC клас (спільний файл)
        if (class_exists('\SEOJusAI\GSC\GscServiceAccount')) {
            GscServiceAccount::get_credentials();
        }
        $key_ready = true;
        $key_msg = '✅ Google Service Account підключено успішно.';
    } catch (\Throwable $e) {
        $key_msg = '❌ Помилка ключа: ' . $e->getMessage();
    }
}

// GA4 Property ID (окремо від ключа)
$ga4_prop = (string) get_option('seojusai_ga4_property_id', '');
?>

<div class="wrap">
    <h1>Налаштування SEOJusAI</h1>

    <nav class="nav-tab-wrapper" style="margin-bottom: 20px;">
        <a href="<?= esc_url(add_query_arg('tab', 'ai', $base_url)) ?>" class="nav-tab <?= $tab === 'ai' ? 'nav-tab-active' : '' ?>">Штучний Інтелект</a>
        <a href="<?= esc_url(add_query_arg('tab', 'data', $base_url)) ?>" class="nav-tab <?= $tab === 'data' ? 'nav-tab-active' : '' ?>">Джерела даних (API)</a>
        <a href="<?= esc_url(add_query_arg('tab', 'features', $base_url)) ?>" class="nav-tab <?= $tab === 'features' ? 'nav-tab-active' : '' ?>">Feature Flags</a>
        <a href="<?= esc_url(add_query_arg('tab', 'autopilot', $base_url)) ?>" class="nav-tab <?= $tab === 'autopilot' ? 'nav-tab-active' : '' ?>"><?php echo esc_html__('Автопілот', 'seojusai'); ?></a>
    </nav>

    <?php if ($tab === 'ai'): ?>

    <div class="card" style="max-width: 100%; margin-top: 0; margin-bottom: 20px; border-left: 4px solid #2271b1;">
        <h2 style="margin-top: 0;">📊 Стан балансу AI</h2>
        <?php
            $credits = class_exists(CreditManager::class) ? CreditManager::get_balance() : 0;
            $color = $credits > 0 ? '#26a69a' : '#d32f2f';
        ?>
        <p style="font-size: 18px;">
            Доступно запитів: <strong style="color: <?= $color ?>; font-size: 24px;"><?= esc_html((string)$credits) ?></strong>
        </p>
        <p class="description">Один запит до AI (аналіз або чат) списує 1 кредит.</p>
    </div>

    <form method="post" action="<?= esc_url(add_query_arg('tab', 'ai', $base_url)) ?>">
        <?php wp_nonce_field('seojusai_ai_action'); ?>
        <h2>Параметри Моделей</h2>
        <table class="form-table">
            <tr>
                <th>OpenAI API Key</th>
                <td>
                    <input type="password" name="openai_key" value="<?= esc_attr(get_option('seojusai_openai_key', '')) ?>" class="regular-text">
                    <p class="description">Необхідний для роботи "AI-ядра" та аналізу контенту.</p>
                </td>
            </tr>
            <tr>
                <th>Основна модель</th>
                <td>
                    <?php $current_model = get_option('seojusai_openai_model', 'gpt-4o-mini'); ?>
                    <select name="openai_model">
                        <option value="gpt-4o" <?php selected($current_model, 'gpt-4o'); ?>>GPT-4o (Краща якість)</option>
                        <option value="gpt-4o-mini" <?php selected($current_model, 'gpt-4o-mini'); ?>>GPT-4o-mini (Швидко та дешево)</option>
                    </select>
                </td>
            </tr>
            <tr>
                <th>Google Gemini Key</th>
                <td>
                    <input type="password" name="gemini_key" value="<?= esc_attr(get_option('seojusai_gemini_key', '')) ?>" class="regular-text">
                    <p class="description">Резервна модель для аналізу великих текстів.</p>
                </td>
            </tr>
        </table>
        <p class="submit"><input type="submit" name="seojusai_save_ai" class="button button-primary" value="Зберегти налаштування AI"></p>
    </form>

    <?php else: ?>
    <form method="post" action="<?= esc_url(add_query_arg('tab', 'data', $base_url)) ?>">
        <?php wp_nonce_field('seojusai_data_action'); ?>

        <h2>Google Search Console та Google Analytics 4 (GA4)</h2>
        <div class="notice inline <?= $key_ready ? 'notice-success' : 'notice-warning' ?>" style="padding: 15px; margin-bottom: 20px; border-left-width: 4px;">
            <p><strong>Статус:</strong> <?= esc_html($key_msg) ?></p>
            <p><small>Шлях до файлу: <code>wp-content/uploads/seojusai/keys/gsc-service-account.json</code></small></p>
            <p><small>Property GA4 ID: <code><?= esc_html($ga4_prop !== '' ? $ga4_prop : 'не задано') ?></code></small></p>
        </div>

        <table class="form-table" style="margin-top: -10px;">
            <tr>
                <th>GA4 Property ID</th>
                <td>
                    <input type="text" name="ga4_property_id" value="<?= esc_attr(get_option('seojusai_ga4_property_id', '')) ?>" class="regular-text" placeholder="123456789 або properties/123456789">
                    <p class="description">Вкажіть GA4 Property ID. Service Account повинен мати доступ до цієї властивості.</p>
                </td>
            </tr>
<tr>
    <th>GSC Ресурс (property)</th>
    <td>
        <input type="text" name="gsc_site" value="<?= esc_attr(get_option('seojusai_gsc_site', '')) ?>" class="regular-text" placeholder="sc-domain:example.com або https://example.com/">
        <p class="description">Не обов'язково. Якщо порожньо — плагін сам обере ресурс зі списку доступних. Якщо Rank Math показує дані, а тут ні — вставте точний ресурс з Search Console (URL-prefix або sc-domain).</p>
    </td>
</tr>

        </table>

        <h2>Інші сервіси</h2>
        <table class="form-table">
            <tr>
                <th>PageSpeed Insights API Key</th>
                <td><input type="text" name="pagespeed_key" value="<?= esc_attr(get_option('seojusai_pagespeed_key', '')) ?>" class="regular-text"></td>
            </tr>
            <tr>
                <th>SerpAPI Key</th>
                <td><input type="text" name="serp_key" value="<?= esc_attr(get_option('seojusai_serp_key', '')) ?>" class="regular-text"></td>
            </tr>
        </table>
        <p class="submit"><input type="submit" name="seojusai_save_data" class="button button-primary" value="Зберегти ключі API"></p>
    </form>
    <?php endif; ?>

<?php if ($tab === 'features'): ?>
    <?php FeatureResolver::ensure_defaults(); $vals = FeatureResolver::get_all(); ?>
    <div class="card" style="max-width: 100%; margin-top: 0; margin-bottom: 20px; border-left: 4px solid #00a32a;">
        <h2 style="margin-top: 0;">🚩 Feature Flags</h2>
        <p>Керуйте експериментальними та стабільними можливостями без ризику для продакшену. Рекомендовано: експериментальні — вимкнені.</p>
        <form method="post">
            <?php wp_nonce_field('seojusai_features_action'); ?>
            <table class="widefat striped" style="margin-top: 10px;">
                <thead>
                    <tr>
                        <th style="width: 60px;">Стан</th>
                        <th>Прапорець</th>
                        <th>Опис</th>
                        <th style="width: 120px;">Рівень</th>
                        <th style="width: 80px;">З версії</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach (FeatureRegistry::all() as $flag): ?>
                        <?php $on = !empty($vals[$flag->key]); ?>
                        <tr>
                            <td>
                                <label>
                                    <input type="checkbox" name="flags[<?= esc_attr($flag->key) ?>]" <?= $on ? 'checked' : '' ?> />
                                </label>
                            </td>
                            <td><code><?= esc_html($flag->key) ?></code><br><strong><?= esc_html($flag->title) ?></strong></td>
                            <td><?= esc_html($flag->description) ?></td>
                            <td>
                                <?php if ($flag->stability === 'experimental'): ?>
                                    <span class="badge" style="background:#d63638;color:#fff;padding:2px 6px;border-radius:10px;">experimental</span>
                                <?php else: ?>
                                    <span class="badge" style="background:#00a32a;color:#fff;padding:2px 6px;border-radius:10px;">stable</span>
                                <?php endif; ?>
                            </td>
                            <td><?= esc_html($flag->since) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <p style="margin-top: 12px;">
                <button type="submit" class="button button-primary" name="seojusai_save_features" value="1">Зберегти Feature Flags</button>
            </p>
        </form>
    </div>
<?php endif; ?>

<?php if ($tab === 'autopilot'): ?>
    <?php
        $opt = get_option('seojusai_autopilot', []);
        if (!is_array($opt)) $opt = [];
        $mode = sanitize_key((string)($opt['mode'] ?? 'shadow'));
        if (!in_array($mode, ['shadow','limited','full'], true)) $mode = 'shadow';
        $allow_apply = !empty($opt['allow_apply']);
        $full_safe_enabled = class_exists(FeatureResolver::class) ? FeatureResolver::enabled('autopilot_full_safe_mode_v1') : false;

        $st = class_exists(AutopilotReliability::class) ? AutopilotReliability::status() : ['paused'=>false,'reason'=>'','since'=>0];
        $thr = class_exists(AutopilotReliability::class) ? AutopilotReliability::thresholds() : ['min_confidence'=>0.70,'max_fail_rate'=>0.25,'min_sample'=>10];
        $health = class_exists(AutopilotReliability::class) ? AutopilotReliability::health() : [];
        $paused = !empty($st['paused']);
        $since = !empty($st['since']) ? date_i18n('Y-m-d H:i:s', (int)$st['since']) : '';
        $fail_rate = isset($health['fail_rate']) ? (float)$health['fail_rate'] : 0.0;
        $sample = isset($health['sample']) ? (int)$health['sample'] : 0;
        $applied = isset($health['applied']) ? (int)$health['applied'] : 0;
        $failed = isset($health['failed']) ? (int)$health['failed'] : 0;
        $rejected = isset($health['rejected']) ? (int)$health['rejected'] : 0;
        $high_risk = isset($health['high_risk']) ? (int)$health['high_risk'] : 0;
    ?>

    <div class="card" style="max-width:100%; margin-top:0; margin-bottom:20px; border-left:4px solid #2271b1;">
        <h2 style="margin-top:0;">🧠 Автопілот — Надійність</h2>
        <p class="description">
            <?php echo esc_html__('Цей блок керує довірою: автопауза при збої/ризику, поріг confidence для auto-apply, та швидке відновлення.', 'seojusai'); ?>
        </p>

        <div class="notice inline <?php echo $paused ? 'notice-warning' : 'notice-success'; ?>" style="padding:12px; border-left-width:4px;">
            <p style="margin:0;">
                <strong><?php echo esc_html__('Статус:', 'seojusai'); ?></strong>
                <?php echo $paused ? esc_html__('PAUSED', 'seojusai') : esc_html__('ACTIVE', 'seojusai'); ?>
                <?php if ($paused): ?>
                    <br/>
                    <strong><?php echo esc_html__('Причина:', 'seojusai'); ?></strong>
                    <code><?php echo esc_html((string)$st['reason']); ?></code>
                    <?php if ($since): ?>
                        <br/><strong><?php echo esc_html__('З:', 'seojusai'); ?></strong> <?php echo esc_html($since); ?>
                    <?php endif; ?>
                <?php endif; ?>
            </p>
        </div>

        <h3 style="margin-top:16px;"><?php echo esc_html__('Health (останні 14 днів)', 'seojusai'); ?></h3>
        <table class="widefat striped" style="max-width:900px;">
            <tbody>
                <tr><th style="width:220px;"><?php echo esc_html__('Applied', 'seojusai'); ?></th><td><?php echo esc_html((string)$applied); ?></td></tr>
                <tr><th><?php echo esc_html__('Failed', 'seojusai'); ?></th><td><?php echo esc_html((string)$failed); ?></td></tr>
                <tr><th><?php echo esc_html__('Rejected', 'seojusai'); ?></th><td><?php echo esc_html((string)$rejected); ?></td></tr>
                <tr><th><?php echo esc_html__('Sample (applied+failed)', 'seojusai'); ?></th><td><?php echo esc_html((string)$sample); ?></td></tr>
                <tr><th><?php echo esc_html__('Fail rate', 'seojusai'); ?></th><td><code><?php echo esc_html((string)$fail_rate); ?></code></td></tr>
                <tr><th><?php echo esc_html__('High-risk (detected)', 'seojusai'); ?></th><td><code><?php echo esc_html((string)$high_risk); ?></code></td></tr>
            </tbody>
        </table>
    </div>

    <form method="post" action="<?= esc_url(add_query_arg('tab', 'autopilot', $base_url)) ?>">
        <?php wp_nonce_field('seojusai_autopilot_ui_action'); ?>

        <div class="card" style="max-width:100%; margin-top:0; margin-bottom:20px; border-left:4px solid #00a32a;">
            <h2 style="margin-top:0;">⚙️ Режим</h2>
            <table class="form-table">
                <tr>
                    <th><?php echo esc_html__('Режим', 'seojusai'); ?></th>
                    <td>
                        <select name="autopilot_mode">
                            <option value="shadow" <?php selected($mode, 'shadow'); ?>>shadow (тільки лог)</option>
                            <option value="limited" <?php selected($mode, 'limited'); ?>>limited (тільки tasks)</option>
                            <option value="full" <?php selected($mode, 'full'); ?>>full (SAFE auto-apply allowlist)</option>
                        </select>
                        <p class="description"><?php echo esc_html__('Full не означає “безконтрольно”. Auto-apply працює лише при allow_apply + feature flag + confidence gate.', 'seojusai'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th><?php echo esc_html__('Дозволити застосування', 'seojusai'); ?></th>
                    <td>
                        <label><input type="checkbox" name="autopilot_allow_apply" value="1" <?php checked($allow_apply); ?> /> <?php echo esc_html__('Дозволити SAFE застосування (лише при full)', 'seojusai'); ?></label>
                    </td>
                </tr>
                <tr>
                    <th><?php echo esc_html__('Full SAFE mode v1 (feature flag)', 'seojusai'); ?></th>
                    <td>
                        <label><input type="checkbox" name="autopilot_full_safe_mode_v1" value="1" <?php checked($full_safe_enabled); ?> /> <?php echo esc_html__('Увімкнути allowlist-автозастосування', 'seojusai'); ?></label>
                    </td>
                </tr>
            </table>
        </div>

        <div class="card" style="max-width:100%; margin-top:0; margin-bottom:20px; border-left:4px solid #dba617;">
            <h2 style="margin-top:0;">🛡️ Надійність (пороги)</h2>
            <table class="form-table">
                <tr>
                    <th><?php echo esc_html__('Min confidence для auto-apply', 'seojusai'); ?></th>
                    <td>
                        <input type="number" step="0.01" min="0" max="1" name="reliability_min_confidence" value="<?php echo esc_attr((string)$thr['min_confidence']); ?>" style="width:120px;"/>
                        <p class="description"><?php echo esc_html__('Якщо confidence нижче — рішення піде тільки у review task.', 'seojusai'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th><?php echo esc_html__('Max fail rate (autopause)', 'seojusai'); ?></th>
                    <td>
                        <input type="number" step="0.01" min="0" max="1" name="reliability_max_fail_rate" value="<?php echo esc_attr((string)$thr['max_fail_rate']); ?>" style="width:120px;"/>
                        <p class="description"><?php echo esc_html__('Якщо failed/(applied+failed) перевищує поріг — автопілот ставиться на PAUSED.', 'seojusai'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th><?php echo esc_html__('Min sample для fail rate', 'seojusai'); ?></th>
                    <td>
                        <input type="number" step="1" min="5" max="200" name="reliability_min_sample" value="<?php echo esc_attr((string)$thr['min_sample']); ?>" style="width:120px;"/>
                    </td>
                </tr>
            </table>

            <p style="margin-top: 12px;">
                <?php if ($paused): ?>
                    <button type="submit" class="button button-primary" name="seojusai_autopilot_resume" value="1"><?php echo esc_html__('Resume', 'seojusai'); ?></button>
                <?php else: ?>
                    <button type="submit" class="button button-secondary" name="seojusai_autopilot_pause" value="1"><?php echo esc_html__('Pause', 'seojusai'); ?></button>
                <?php endif; ?>
                <button type="submit" class="button button-primary" name="seojusai_save_autopilot_ui" value="1" style="margin-left:10px;"><?php echo esc_html__('Зберегти', 'seojusai'); ?></button>
            </p>
        </div>
    </form>
<?php endif; ?>


</div>
