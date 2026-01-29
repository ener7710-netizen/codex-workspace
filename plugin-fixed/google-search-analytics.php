<?php
declare(strict_types=1);

use SEOJusAI\GSC\GSCClient;
use SEOJusAI\GSC\GscSnapshot;

defined('ABSPATH') || exit;

if ( ! current_user_can('manage_options') ) {
	return;
}

echo '<div class="wrap">';
echo '<h1>Пошукова аналітика — Google Search Console</h1>';
echo '<div class="seojusai-card">';
echo '<h2>' . esc_html__('Перевірка підключення Google Search Console', 'seojusai') . '</h2>';
echo '<p>' . esc_html__('Натисніть кнопку, щоб перевірити підключення та завантажити список доступних ресурсів.', 'seojusai') . '</p>';
echo '<p><a href="#" id="seojusai-gsc-refresh" class="button button-primary">' . esc_html__('Перевірити підключення', 'seojusai') . '</a></p>';
echo '<div id="seojusai-gsc-status" class="seojusai-status seojusai-status-info">' . esc_html__('Очікує перевірки.', 'seojusai') . '</div>';
echo '<div id="seojusai-gsc-output" class="seojusai-gsc-output"></div>';
echo '</div>';


$client = new GSCClient();

/* ================= PROPERTIES ================= */

$sites = $client->list_properties();

if ( empty($sites) ) {
	echo '<div class="notice notice-warning"><p>';
	echo '❗ Дані GSC недоступні.<br>';
	echo 'Перевір:<br>';
	echo '• Service Account доданий у Google Search Console<br>';
	echo '• Є доступ до сайту<br>';
	echo '• Файл gsc-service-account.json існує';
	echo '</p></div>';
	echo '</div>';
	return;
}

echo '<h2>Підключені сайти</h2><ul>';
foreach ( $sites as $site ) {
	echo '<li><strong>' . esc_html($site) . '</strong></li>';
}
echo '</ul>';

/* ================= ANALYTICS ================= */

$site = $sites[0];

$data = $client->get_search_analytics($site);

/* ===== SNAPSHOT (GSC) ===== */
\SEOJusAI\GSC\GscSnapshot::save($site, $data);

echo '<h2>Search Analytics (останні 28 днів)</h2>';

if ( empty($data) ) {
	echo '<p>Немає даних</p>';
	echo '</div>';
	return;
}

echo '<table class="widefat striped"><thead><tr>
<th>Запит</th>
<th>Сторінка</th>
<th>Кліки</th>
<th>Покази</th>
<th>CTR</th>
<th>Позиція</th>
</tr></thead><tbody>';

foreach ( $data as $row ) {
	$keys = $row['keys'] ?? [];
	echo '<tr>';
	echo '<td>' . esc_html($keys[0] ?? '-') . '</td>';
	echo '<td>' . esc_html($keys[1] ?? '-') . '</td>';
	echo '<td>' . esc_html((string) ($row['clicks'] ?? 0)) . '</td>';
	echo '<td>' . esc_html((string) ($row['impressions'] ?? 0)) . '</td>';
	echo '<td>' . esc_html(round((float) ($row['ctr'] ?? 0) * 100, 2)) . '%</td>';
	echo '<td>' . esc_html(round((float) ($row['position'] ?? 0), 2)) . '</td>';
	echo '</tr>';
}

echo '</tbody></table></div>';
