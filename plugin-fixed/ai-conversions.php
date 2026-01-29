<?php
namespace SEOJusAI\Admin\Pages;

use SEOJusAI\Core\I18n;

defined('ABSPATH') || exit;
if ( ! current_user_can('manage_options') ) { return; }

?>
<div class="wrap seojusai-wrap">
  <div class="seojusai-page-header">
    <h1>Конверсії штучного інтелекту</h1>
    <p class="description"><?php echo esc_html(I18n::t('Оцінка впливу рекомендацій та змін SEOJusAI на звернення, дзвінки та інші цільові дії.')); ?></p>
  </div>

  <div class="seojusai-grid">
    <div class="seojusai-card">
      <h2><?php echo esc_html(I18n::t('Поточний стан')); ?></h2>
      <p class="seojusai-muted"><?php echo esc_html(I18n::t('Дані зʼявляться після підключення джерел аналітики або імпорту подій.')); ?></p>
      <div class="seojusai-empty"><?php echo esc_html(I18n::t('Наразі дані відсутні.')); ?></div>
    </div>

    <div class="seojusai-card">
      <h2><?php echo esc_html(I18n::t('Що рахується конверсією')); ?></h2>
      <ul class="seojusai-list">
        <li><?php echo esc_html(I18n::t('Натискання на номер телефону або кнопку звʼязку')); ?></li>
        <li><?php echo esc_html(I18n::t('Відправка форми звернення')); ?></li>
        <li><?php echo esc_html(I18n::t('Перехід у месенджери/чат')); ?></li>
        <li><?php echo esc_html(I18n::t('Інші події, визначені у налаштуваннях')); ?></li>
      </ul>
    </div>
  </div>
</div>
