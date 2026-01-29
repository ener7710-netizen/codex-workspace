<?php
namespace SEOJusAI\Admin\Pages;

use SEOJusAI\Core\I18n;

defined('ABSPATH') || exit;

if ( ! current_user_can('manage_options') ) { return; }

?>
<div class="wrap seojusai-wrap">
  <div class="seojusai-page-header">
    <h1><?php echo esc_html(I18n::t('Задачі')); ?></h1>
    <p class="description"><?php echo esc_html(I18n::t('Черга та історія задач: аудит, групова обробка, застосування та відкат.')); ?></p>
  </div>

  <div class="seojusai-grid">
    <div class="seojusai-card">
      <h2><?php echo esc_html(I18n::t('Останні задачі')); ?></h2>
      <p class="seojusai-muted"><?php echo esc_html(I18n::t('Тут відображаються останні запуски аудитів і групових операцій.')); ?></p>
      <div class="seojusai-empty">
        <?php echo esc_html(I18n::t('Поки що немає задач для відображення. Запустіть аудит або групову обробку.')); ?>
      </div>
    </div>

    <div class="seojusai-card">
      <h2><?php echo esc_html(I18n::t('Стан системи задач')); ?></h2>
      <ul class="seojusai-list">
        <li><strong><?php echo esc_html(I18n::t('Обробник задач')); ?>:</strong> <?php echo esc_html(I18n::t('Активний')); ?></li>
        <li><strong><?php echo esc_html(I18n::t('Збереження станів')); ?>:</strong> <?php echo esc_html(I18n::t('Доступно')); ?></li>
        <li><strong><?php echo esc_html(I18n::t('Безпечний режим')); ?>:</strong> <?php echo esc_html(I18n::t('Вимкнено')); ?></li>
      </ul>
    </div>
  </div>
</div>
