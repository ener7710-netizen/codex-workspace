<?php
declare(strict_types=1);

namespace SEOJusAI\Admin\Pages;

use SEOJusAI\Input\Input;
use SEOJusAI\Snapshots\SnapshotRepository;

defined('ABSPATH') || exit;

if (!current_user_can('manage_options')) {
	return;
}

$url = (Input::get('url', null) !== null) ? esc_url_raw((string) Input::get('url')) : (string) home_url('/');
$strategy = (Input::get('strategy', null) !== null) ? sanitize_key((string) Input::get('strategy')) : 'mobile';
$strategy = ($strategy === 'desktop') ? 'desktop' : 'mobile';

$notice = (Input::get('seojusai_notice', null) !== null) ? sanitize_key((string) Input::get('seojusai_notice')) : '';

echo '<div class="wrap">';
echo '<h1>' . esc_html__('Швидкість та показники сторінок', 'seojusai') . '</h1>';

if ($notice === 'pagespeed_enqueued') {
	echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Завдання PageSpeed додано в чергу. Оновіть сторінку через 10–30 секунд.', 'seojusai') . '</p></div>';
} elseif ($notice === 'pagespeed_failed') {
	echo '<div class="notice notice-error is-dismissible"><p>' . esc_html__('Не вдалося додати PageSpeed-завдання в чергу.', 'seojusai') . '</p></div>';
}

echo '<div class="seojusai-card" style="max-width: 980px;">';
echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" style="margin:0;">';
wp_nonce_field('seojusai_pagespeed_snapshot');
echo '<input type="hidden" name="action" value="seojusai_pagespeed_snapshot" />';
echo '<table class="form-table" role="presentation"><tbody>';
echo '<tr><th scope="row" style="width:160px;">' . esc_html__('URL', 'seojusai') . '</th>';
echo '<td><input class="regular-text" type="text" name="pagespeed_url" value="' . esc_attr($url) . '" /></td></tr>';
echo '<tr><th scope="row">' . esc_html__('Стратегія', 'seojusai') . '</th><td>';
echo '<select name="pagespeed_strategy">';
echo '<option value="mobile"' . selected($strategy, 'mobile', false) . '>' . esc_html__('Мобільна', 'seojusai') . '</option>';
echo '<option value="desktop"' . selected($strategy, 'desktop', false) . '>' . esc_html__('Десктоп', 'seojusai') . '</option>';
echo '</select>';
echo '</td></tr>';
echo '</tbody></table>';
echo '<p><button class="button button-primary">' . esc_html__('Оновити та просканувати', 'seojusai') . '</button></p>';
echo '</form>';
echo '</div>';

$repo = new SnapshotRepository();
$latest = null;

if ($repo->exists()) {
	// Pull the latest snapshots and pick the first matching URL+strategy (data_json filtering is DB-dependent).
	global $wpdb;
	$table = $wpdb->prefix . 'seojusai_snapshots';
	$rows = $wpdb->get_results(
		$wpdb->prepare("SELECT * FROM {$table} WHERE entity_type = %s ORDER BY id DESC LIMIT 25", 'pagespeed'),
		ARRAY_A
	);
	foreach ($rows as $row) {
		$data = json_decode((string) ($row['data_json'] ?? ''), true);
		if (!is_array($data)) continue;
		$u = (string) ($data['url'] ?? '');
		$s = (string) ($data['strategy'] ?? '');
		if ($u === $url && $s === $strategy) {
			$row['data'] = $data;
			$latest = $row;
			break;
		}
	}
}

echo '<h2 style="margin-top:18px;">' . esc_html__('Останній результат', 'seojusai') . '</h2>';

if (!$latest) {
	echo '<div class="notice notice-info"><p>' . esc_html__('Дані ще не зібрано. Запустіть сканування — результат з’явиться після виконання задачі в черзі.', 'seojusai') . '</p></div>';
	echo '</div>';
	return;
}

$data = is_array($latest['data'] ?? null) ? $latest['data'] : [];
$ok = !empty($data['ok']);
$perf = isset($data['performance']) && is_numeric($data['performance']) ? (int) round(((float) $data['performance']) * 100) : null;

echo '<div class="seojusai-card" style="max-width: 980px;">';
echo '<p><strong>' . esc_html__('Статус', 'seojusai') . ':</strong> ' . ($ok ? '✅ ' . esc_html__('OK', 'seojusai') : '⚠️ ' . esc_html__('Помилка', 'seojusai')) . '</p>';
echo '<p><strong>' . esc_html__('Performance', 'seojusai') . ':</strong> ' . esc_html((string) ($perf ?? '—')) . '</p>';

if (!$ok) {
	echo '<p><strong>HTTP:</strong> ' . esc_html((string) ((int) ($data['http_code'] ?? 0))) . '</p>';
	echo '<p><strong>' . esc_html__('Деталі', 'seojusai') . ':</strong> ' . esc_html((string) ($data['error'] ?? '')) . '</p>';
} else {
	echo '<table class="widefat striped" style="max-width:680px;">';
	echo '<thead><tr><th>' . esc_html__('Метрика', 'seojusai') . '</th><th>' . esc_html__('Значення', 'seojusai') . '</th></tr></thead><tbody>';
	echo '<tr><td>FCP</td><td>' . esc_html((string) ((isset($data['fcp_ms']) && is_numeric($data['fcp_ms'])) ? round((float) $data['fcp_ms']) . ' ms' : '—')) . '</td></tr>';
	echo '<tr><td>LCP</td><td>' . esc_html((string) ((isset($data['lcp_ms']) && is_numeric($data['lcp_ms'])) ? round((float) $data['lcp_ms']) . ' ms' : '—')) . '</td></tr>';
	echo '<tr><td>CLS</td><td>' . esc_html((string) ((isset($data['cls']) && is_numeric($data['cls'])) ? (string) $data['cls'] : '—')) . '</td></tr>';
	echo '<tr><td>TBT</td><td>' . esc_html((string) ((isset($data['tbt_ms']) && is_numeric($data['tbt_ms'])) ? round((float) $data['tbt_ms']) . ' ms' : '—')) . '</td></tr>';
	echo '</tbody></table>';
}

echo '<p style="margin-top:12px;color:#646970;">' . esc_html__('Порада: для стабільності використовуйте чергу завдань. Сторінка не виконує API-запит синхронно.', 'seojusai') . '</p>';
echo '</div>';
echo '</div>';
