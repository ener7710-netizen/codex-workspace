<?php
declare(strict_types=1);

use SEOJusAI\Opportunity\OpportunityEngine;
use SEOJusAI\Core\I18n;

defined('ABSPATH') || exit;
if (!current_user_can('manage_options')) { return; }

echo '<div class="wrap">';
echo '<h1>' . esc_html(I18n::t('Пріоритети SEO (Opportunity)')) . '</h1>';
echo '<p>' . esc_html(I18n::t('Сторінки з найбільшим потенціалом росту на основі даних GSC (покази/позиція/CTR) та якості контенту.')) . '</p>';

$engine = new OpportunityEngine();
$rows = $engine->compute(100);

if (empty($rows)) {
	echo '<div class="notice notice-warning"><p>' . esc_html(I18n::t('Немає даних. Спочатку імпортуйте Search Analytics у модулі Google Search Console.')) . '</p></div>';
	echo '</div>';
	return;
}

echo '<table class="widefat striped"><thead><tr>';
echo '<th>' . esc_html(I18n::t('Сторінка')) . '</th>';
echo '<th>' . esc_html(I18n::t('Покази')) . '</th>';
echo '<th>' . esc_html(I18n::t('Кліки')) . '</th>';
echo '<th>' . esc_html(I18n::t('Позиція')) . '</th>';
echo '<th>' . esc_html(I18n::t('CTR %')) . '</th>';
echo '<th>' . esc_html(I18n::t('Оцінка контенту')) . '</th>';
echo '<th>' . esc_html(I18n::t('Opportunity')) . '</th>';
echo '</tr></thead><tbody>';

foreach ($rows as $r) {
	$url = (string) ($r['url'] ?? '');
	$post_id = (int) ($r['post_id'] ?? 0);
	$title = $post_id ? get_the_title($post_id) : $url;
	$edit = $post_id ? get_edit_post_link($post_id, '') : '';
	echo '<tr>';
	echo '<td>';
	if ($edit) {
		echo '<a href="' . esc_url($edit) . '">' . esc_html($title) . '</a><br><code>' . esc_html($url) . '</code>';
	} else {
		echo '<code>' . esc_html($url) . '</code>';
	}
	echo '</td>';
	echo '<td>' . esc_html((string)($r['impressions'] ?? '0')) . '</td>';
	echo '<td>' . esc_html((string)($r['clicks'] ?? '0')) . '</td>';
	echo '<td>' . esc_html((string)($r['position'] ?? '—')) . '</td>';
	echo '<td>' . esc_html((string)($r['ctr'] ?? '—')) . '</td>';
	echo '<td>' . esc_html((string)($r['content_score'] ?? '—')) . '</td>';
	echo '<td><strong>' . esc_html((string)($r['opportunity'] ?? '—')) . '</strong></td>';
	echo '</tr>';
}

echo '</tbody></table>';
echo '</div>';
