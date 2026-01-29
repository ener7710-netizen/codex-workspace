<?php
declare(strict_types=1);

use SEOJusAI\Redirects\RedirectRepository;
use SEOJusAI\Redirects\NotFoundLogger;
use SEOJusAI\Redirects\RankMath404Importer;
use SEOJusAI\Input\Input;

defined('ABSPATH') || exit;

if (!current_user_can('manage_options')) { return; }

$repo = new RedirectRepository();
$logger = new NotFoundLogger();

$notice_html = '';

// Optional import from Rank Math (if installed) to seed 404 list.
if ((Input::get('seojusai_action', null) !== null) && ((string) Input::get('seojusai_action') === 'import_rankmath_404')) {
	if (!check_admin_referer('seojusai_import_rankmath_404')) {
		$notice_html = '<div class="notice notice-error"><p>' . esc_html__('Невірний nonce. Спробуйте ще раз.', 'seojusai') . '</p></div>';
	} else {
		$importer = new RankMath404Importer();
		if (!$importer->is_available()) {
			$notice_html = '<div class="notice notice-warning"><p>' . esc_html__('Rank Math 404-журнал не знайдено. Переконайтеся, що увімкнено 404 Monitor у Rank Math.', 'seojusai') . '</p></div>';
		} else {
			$rows = $importer->fetch_rows(500);
			$imported = $logger->import_rows($rows);
			$notice_html = '<div class="notice notice-success"><p>' . sprintf(
				/* translators: %d: imported rows */
				esc_html__('Імпортовано 404-записів: %d', 'seojusai'),
				(int) $imported
			) . '</p></div>';
		}
	}
}

if ((Input::post('seojusai_redirect_nonce', null) !== null) && wp_verify_nonce((string) Input::post('seojusai_redirect_nonce'), 'seojusai_redirect_save')) {
	$from = (Input::post('from_path', null) !== null) ? (string) wp_unslash(Input::post('from_path')) : '';
	$to   = (Input::post('to_url', null) !== null) ? (string) wp_unslash(Input::post('to_url')) : '';
	$code = (Input::post('code', null) !== null) ? (int) Input::post('code') : 301;
	$repo->upsert($from, $to, $code);
	echo '<div class="notice notice-success"><p>' . esc_html__('Редирект збережено.', 'seojusai') . '</p></div>';
}

if ((Input::get('delete', null) !== null) && check_admin_referer('seojusai_redirect_delete')) {
	$repo->delete((int) Input::get('delete'));
	echo '<div class="notice notice-success"><p>' . esc_html__('Редирект видалено.', 'seojusai') . '</p></div>';
}

echo '<div class="wrap">';
echo '<h1>' . esc_html__('Редиректи та 404', 'seojusai') . '</h1>';

if ($notice_html !== '') {
	echo $notice_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
}

if ($notice_html !== '') {
	echo $notice_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
}

echo '<h2>' . esc_html__('Додати/оновити редирект', 'seojusai') . '</h2>';
echo '<form method="post" style="max-width: 900px;">';
wp_nonce_field('seojusai_redirect_save', 'seojusai_redirect_nonce');
echo '<table class="form-table"><tbody>';
echo '<tr><th><label>' . esc_html__('Звідки (шлях)', 'seojusai') . '</label></th><td><input name="from_path" class="regular-text" placeholder="/stara-storinka/" required></td></tr>';
echo '<tr><th><label>' . esc_html__('Куди (URL)', 'seojusai') . '</label></th><td><input name="to_url" class="regular-text" placeholder="https://..." required></td></tr>';
echo '<tr><th><label>' . esc_html__('Код', 'seojusai') . '</label></th><td><select name="code"><option value="301">301</option><option value="302">302</option><option value="307">307</option><option value="308">308</option></select></td></tr>';
echo '</tbody></table>';
submit_button(__('Зберегти', 'seojusai'));
echo '</form>';

echo '<hr/>';

echo '<h2>' . esc_html__('Активні редиректи', 'seojusai') . '</h2>';
if (!$repo->exists()) {
	echo '<p>' . esc_html__('Таблиця редиректів не знайдена. Активуйте плагін повторно або запустіть інсталятор.', 'seojusai') . '</p>';
} else {
	$rows = $repo->all(300);
	echo '<table class="widefat striped"><thead><tr><th>' . esc_html__('Звідки', 'seojusai') . '</th><th>' . esc_html__('Куди', 'seojusai') . '</th><th>' . esc_html__('Код', 'seojusai') . '</th><th>' . esc_html__('Хіти', 'seojusai') . '</th><th></th></tr></thead><tbody>';
	if (empty($rows)) {
		echo '<tr><td colspan="5">' . esc_html__('Немає редиректів', 'seojusai') . '</td></tr>';
	} else {
		foreach ($rows as $r) {
			$del = wp_nonce_url(admin_url('admin.php?page=seojusai-redirects&delete=' . (int)$r['id']), 'seojusai_redirect_delete');
			echo '<tr>';
			echo '<td><code>' . esc_html($r['from_path']) . '</code></td>';
			echo '<td><a href="' . esc_url($r['to_url']) . '" target="_blank" rel="noopener">' . esc_html($r['to_url']) . '</a></td>';
			echo '<td>' . esc_html((string)$r['code']) . '</td>';
			echo '<td>' . esc_html((string)$r['hits']) . '</td>';
			echo '<td><a class="button button-link-delete" href="' . esc_url($del) . '">' . esc_html__('Видалити', 'seojusai') . '</a></td>';
			echo '</tr>';
		}
	}
	echo '</tbody></table>';
}

echo '<hr/>';

echo '<h2>' . esc_html__('Топ 404 (останні)', 'seojusai') . '</h2>';

$import_url = wp_nonce_url(
	admin_url('admin.php?page=seojusai-redirects&seojusai_action=import_rankmath_404'),
	'seojusai_import_rankmath_404'
);
echo '<p><a class="button" href="' . esc_url($import_url) . '">' . esc_html__('Імпортувати 404 з Rank Math', 'seojusai') . '</a></p>';
if (!$logger->exists()) {
	echo '<p>' . esc_html__('Таблиця 404 не знайдена. Активуйте плагін повторно або запустіть інсталятор.', 'seojusai') . '</p>';
} else {
	$rows = $logger->top(100);
	echo '<table class="widefat striped"><thead><tr><th>' . esc_html__('URL', 'seojusai') . '</th><th>' . esc_html__('Хіти', 'seojusai') . '</th><th>' . esc_html__('Останній раз', 'seojusai') . '</th></tr></thead><tbody>';
	if (empty($rows)) {
		echo '<tr><td colspan="3">' . esc_html__('Немає 404', 'seojusai') . '</td></tr>';
	} else {
		foreach ($rows as $r) {
			echo '<tr>';
			echo '<td><code>' . esc_html($r['url']) . '</code></td>';
			echo '<td>' . esc_html((string)$r['hits']) . '</td>';
			echo '<td>' . esc_html((string)$r['last_seen']) . '</td>';
			echo '</tr>';
		}
	}
	echo '</tbody></table>';
}

echo '</div>';
