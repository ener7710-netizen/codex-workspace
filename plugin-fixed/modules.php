<?php
namespace SEOJusAI\Admin\Pages;

use SEOJusAI\Core\ModuleRegistry;
use SEOJusAI\Core\I18n;

defined('ABSPATH') || exit;

if ( ! current_user_can('manage_options') ) { return; }

$registry = ModuleRegistry::instance();
$modules  = $registry->all();

$nonce = wp_create_nonce('seojusai_toggle_module');

// Типи (вкладки)
$type_map = [
    // Core
    'ai' => 'core',
    'snapshots' => 'core',
    'kbe' => 'core',
    'gsc' => 'core',

    // Advanced
    'serp' => 'advanced',
    'structure' => 'advanced',
    'eeat' => 'advanced',
    'lsi' => 'advanced',
    'linking' => 'advanced',
    'new_pages' => 'advanced',
    'vectors' => 'advanced',
    'learning' => 'advanced',
    'lead_funnel' => 'advanced',
    'experiments' => 'advanced',
    'content_score' => 'advanced',
    'meta' => 'advanced',

    // AI / Decision
    'autopilot' => 'ai',
    'task_state' => 'ai',
    'ai_risk_funnel' => 'ai',
    'case_learning' => 'ai',

    // Infra
    'schema' => 'infra',
    'sitemap' => 'infra',
    'redirects' => 'infra',
    'breadcrumbs' => 'infra',
    'robots' => 'infra',
    'background' => 'infra',

    // Bulk / Workflow
    'bulk' => 'bulk',
];

// Розширені описи (якщо не задано в реєстрі)
$long_desc = [
    'ai' => I18n::t('Виконує аудит сторінок, аналіз структури та формує рекомендації. Є основою для всіх AI-функцій.'),
    'snapshots' => I18n::t('Автоматично створює знімки контенту перед будь-якими змінами. Дозволяє швидкий відкат.'),
    'kbe' => I18n::t('Зберігає знання, кейси та правила. Підсилює якість рекомендацій і E-E-A-T логіку.'),
    'gsc' => I18n::t('Підтягує покази/кліки/позиції/CTR, щоб підказки ґрунтувалися на реальних даних.'),
    'schema' => I18n::t('Формує Schema.org розмітку, перевіряє валідність та конфлікти з іншими SEO-плагінами.'),
    'autopilot' => I18n::t('Об’єднує всі сигнали в план дій. Працює з чергою задач і “людина в контурі”.'),
    'task_state' => I18n::t('Веде журнал задач: що було запущено, що завершено, які зміни запропоновані/застосовані.'),
    'bulk' => I18n::t('Масові операції: аудит групи сторінок, підготовка правок, застосування і відкат.'),
];

// Icon mapping: assign WordPress dashicons to modules for a more visual UI. These
// icons loosely represent the module purpose and help users quickly scan the list.
$icon_map = [
    'ai'         => 'dashicons-analytics',
    'snapshots'  => 'dashicons-camera',
    'kbe'        => 'dashicons-book',
    'gsc'        => 'dashicons-chart-bar',
    'serp'       => 'dashicons-search',
    'structure'  => 'dashicons-networking',
    'eeat'       => 'dashicons-universal-access-alt',
    'lsi'        => 'dashicons-tag',
    'linking'    => 'dashicons-admin-links',
    'new_pages'  => 'dashicons-plus-alt',
    'vectors'    => 'dashicons-shield-alt',
    'learning'   => 'dashicons-welcome-learn-more',
    'lead_funnel'=> 'dashicons-filter',
    'experiments'=> 'dashicons-beaker',
    'content_score'=> 'dashicons-editor-spellcheck',
    'meta'       => 'dashicons-media-document',
    'autopilot'  => 'dashicons-controls-repeat',
    'task_state'=> 'dashicons-list-view',
    'ai_risk_funnel' => 'dashicons-warning',
    'case_learning'  => 'dashicons-graduation-cap',
    'schema'     => 'dashicons-code-standards',
    'sitemap'    => 'dashicons-index-card',
    'redirects'  => 'dashicons-randomize',
    'breadcrumbs'=> 'dashicons-menu',
    'robots'     => 'dashicons-admin-site-alt3',
    'background'=> 'dashicons-update',
    'bulk'       => 'dashicons-archive',
];

uasort($modules, static function($a, $b){
    return (int)($a['order'] ?? 999) <=> (int)($b['order'] ?? 999);
});
?>
<div class="wrap seojusai-wrap">
    <h1><?php echo esc_html(I18n::t('Модулі SEOJusAI')); ?></h1>
    <p class="description"><?php echo esc_html(I18n::t('Керуйте функціональністю системи. Усі модулі згруповані за типами.')); ?></p>

    <div class="seojusai-tabs">
        <button type="button" data-tab="core" class="active"><?php echo esc_html('Базовий рівень'); ?></button>
        <button type="button" data-tab="advanced"><?php echo esc_html('Просунутий рівень'); ?></button>
        <button type="button" data-tab="ai"><?php echo esc_html('Прийняття рішень Штучним інтелектом'); ?></button>
        <button type="button" data-tab="infra"><?php echo esc_html('Інфраструктура'); ?></button>
        <button type="button" data-tab="bulk"><?php echo esc_html('Групова обробка'); ?></button>
    </div>

    <div class="seojusai-modules-grid">
    <?php foreach ($modules as $slug => $module):
        $type = $type_map[$slug] ?? 'advanced';
        $ld   = (string)($module['long_description'] ?? ($long_desc[$slug] ?? ''));
    ?>
        <div class="seojusai-module-card <?php echo $module['enabled'] ? 'is-enabled' : 'is-disabled'; ?>"
             data-module="<?php echo esc_attr($slug); ?>"
             data-type="<?php echo esc_attr($type); ?>">
            <div class="seojusai-module-header">
                <h3>
                    <?php
                    // Prepend an icon for each module if available in the map.
                    $icon_class = $icon_map[$slug] ?? 'dashicons-admin-generic';
                    ?>
                    <span class="dashicons <?php echo esc_attr($icon_class); ?>" style="margin-right:6px;"></span>
                    <?php echo esc_html($module['label']); ?>
                </h3>
                <label class="seojusai-switch">
                    <input type="checkbox"
                           class="seojusai-toggle-input"
                           <?php checked($module['enabled'], true); ?>
                           <?php disabled($module['locked'], true); ?> />
                    <span class="seojusai-slider"></span>
                </label>
            </div>

            <div class="seojusai-module-body">
                <p><?php echo esc_html($module['description']); ?></p>
                <?php if ($ld !== ''): ?>
                    <p class="seojusai-module-long"><?php echo esc_html($ld); ?></p>
                <?php endif; ?>

                <?php if (!empty($module['locked'])): ?>
                    <div class="seojusai-locked">
                        <span class="dashicons dashicons-lock"></span>
                        <?php echo esc_html(I18n::t('Системний модуль')); ?>
                    </div>
                <?php endif; ?>
            </div>

            <div class="seojusai-module-footer">
                <span class="seojusai-status-label <?php echo $module['enabled'] ? 'on' : 'off'; ?>">
                    <?php echo esc_html($module['enabled'] ? I18n::t('Активний') : I18n::t('Вимкнений')); ?>
                </span>
            </div>
        </div>
    <?php endforeach; ?>
    </div>
</div>

<style>
.seojusai-tabs{display:flex;gap:8px;margin:16px 0 8px;flex-wrap:wrap}
.seojusai-tabs button{padding:8px 12px;border-radius:8px;border:1px solid #dcdcde;background:#f6f7f7;cursor:pointer}
.seojusai-tabs button.active{background:#22c55e;color:#fff;border-color:#22c55e}
.seojusai-modules-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 20px; margin-top: 12px; }
.seojusai-module-card { background: #fff; border-radius: 12px; padding: 24px; border: 1px solid #dcdcde; transition: 0.3s; }
.seojusai-module-card.is-enabled { border-top: 4px solid #22c55e; }
.seojusai-module-header { display: flex; justify-content: space-between; margin-bottom: 10px; gap: 12px; }
.seojusai-switch { position: relative; width: 40px; height: 20px; display: inline-block; }
.seojusai-switch input { opacity: 0; width: 0; height: 0; }
.seojusai-slider { position: absolute; cursor: pointer; inset: 0; background: #ccc; border-radius: 20px; transition: 0.3s; }
.seojusai-slider:before { content: ""; position: absolute; height: 14px; width: 14px; left: 3px; bottom: 3px; background: white; border-radius: 50%; transition: 0.3s; }
input:checked + .seojusai-slider { background: #22c55e; }
input:checked + .seojusai-slider:before { transform: translateX(20px); }
.seojusai-status-label.on { color: #16a34a; font-weight: bold; }
.seojusai-module-long{margin-top:10px;color:#3c434a}
.seojusai-locked{margin-top:10px;display:flex;gap:6px;align-items:center;color:#8a0000}
</style>

<script>
(function(){
  function applyTab(tab){
    document.querySelectorAll('.seojusai-tabs button').forEach(b=>b.classList.toggle('active', b.dataset.tab===tab));
    document.querySelectorAll('.seojusai-module-card').forEach(c=>{
      c.style.display = (c.dataset.type===tab) ? 'block' : 'none';
    });
  }
  document.querySelectorAll('.seojusai-tabs button').forEach(btn=>{
    btn.addEventListener('click', function(){ applyTab(this.dataset.tab); });
  });
  // default
  applyTab('core');

  document.querySelectorAll('.seojusai-toggle-input').forEach(t => {
    t.addEventListener('change', function() {
      const card = this.closest('.seojusai-module-card');
      const fd = new FormData();
      fd.append('action', 'seojusai_toggle_module');
      fd.append('_ajax_nonce', '<?php echo esc_js($nonce); ?>');
      fd.append('module', card.dataset.module);
      fd.append('enabled', this.checked ? 1 : 0);
      fetch(ajaxurl, { method: 'POST', body: fd });
    });
  });
})();
</script>
