<?php
namespace SEOJusAI\Admin\Pages;

use SEOJusAI\Core\I18n;

defined('ABSPATH') || exit;
if ( ! current_user_can('manage_options') ) { return; }

?>
<div class="wrap seojusai-wrap">
  <div class="seojusai-page-header">
    <h1>Рішення та результат</h1>
    <p class="description"><?php echo esc_html(I18n::t('Журнал ухвалених рішень системою та підсумковий ефект після застосування.')); ?></p>
  </div>

  <div class="seojusai-grid">
    <div class="seojusai-card">
      <h2><?php echo esc_html(I18n::t('Останні рішення')); ?></h2>
      <p class="seojusai-muted"><?php echo esc_html(I18n::t('Тут зʼявляються рекомендації та підтверджені дії з редактора або групової обробки.')); ?></p>
      <div class="seojusai-empty"><?php echo esc_html(I18n::t('Рішень поки немає.')); ?></div>
    </div>

    <div class="seojusai-card">
      <h2><?php echo esc_html(I18n::t('Показник успіху')); ?></h2>
      <ul class="seojusai-list">
        <li><?php echo esc_html(I18n::t('Поліпшення позицій і CTR')); ?></li>
        <li><?php echo esc_html(I18n::t('Зростання показів і кліків')); ?></li>
        <li><?php echo esc_html(I18n::t('Зменшення технічних помилок')); ?></li>
        <li><?php echo esc_html(I18n::t('Стабільність після змін')); ?></li>
      </ul>
    </div>
  </div>
</div>
