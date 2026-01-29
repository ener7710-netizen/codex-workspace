<?php
declare(strict_types=1);

use SEOJusAI\GSC\GscSnapshot;
use SEOJusAI\Input\Input;

defined('ABSPATH') || exit;

if ( ! current_user_can('manage_options') ) {
	return;
}

/* ===== CLEAR ===== */

if ( (Input::post('seojusai_clear_gsc_history', null) !== null) ) {
	check_admin_referer('seojusai_clear_gsc_history');
	GscSnapshot::clear();
	echo '<div class="notice notice-success"><p>Історію GSC очищено.</p></div>';
}

/* ===== LOAD ===== */

$history = GscSnapshot::get_history(30);

echo '<div class="wrap">';
echo '<h1>Історія Google Search Console</h1>';
echo '<div class="seojusai-card">';
echo '<h2>' . esc_html__('Перевірка підключення Google Search Console', 'seojusai') . '</h2>';
echo '<p>' . esc_html__('Натисніть кнопку, щоб перевірити підключення та завантажити список доступних ресурсів.', 'seojusai') . '</p>';
echo '<p><a href="#" id="seojusai-gsc-refresh" class="button button-secondary">' . esc_html__('Перевірити підключення', 'seojusai') . '</a></p>';
echo '<div id="seojusai-gsc-status" class="seojusai-status seojusai-status-info">' . esc_html__('Очікує перевірки.', 'seojusai') . '</div>';
echo '<div id="seojusai-gsc-output" class="seojusai-gsc-output"></div>';
echo '</div>';


echo '<form method="post" style="margin-bottom:15px;">';
wp_nonce_field('seojusai_clear_gsc_history');
echo '<button class="button button-secondary" name="seojusai_clear_gsc_history">
	Очистити історію
</button>';
echo '</form>';

if ( empty($history) ) {
	echo '<p>Історія порожня.</p>';
	echo '</div>';
	return;
}

echo '<table class="widefat striped">';
echo '<thead><tr>
	<th>Дата</th>
	<th>Сайт</th>
	<th>К-сть рядків</th>
</tr></thead><tbody>';

foreach ( $history as $row ) {

	$data = json_decode($row['data_json'], true);
	$site = $data['site'] ?? '—';
	$count = isset($data['rows']) ? count($data['rows']) : 0;

	echo '<tr>';
	echo '<td>' . esc_html($row['created_at']) . '</td>';
	echo '<td>' . esc_html($site) . '</td>';
	echo '<td>' . esc_html((string) $count) . '</td>';
	echo '</tr>';
}

echo '</tbody></table>';
echo '</div>';
