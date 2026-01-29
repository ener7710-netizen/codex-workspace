<?php
declare(strict_types=1);

use SEOJusAI\Tasks\TaskQueue;
use SEOJusAI\Snapshots\SnapshotRepository;

defined('ABSPATH') || exit;

if ( ! current_user_can('manage_options') ) {
	echo '<div class="notice notice-error"><p>' . esc_html__('Недостатньо прав для доступу.', 'seojusai') . '</p></div>';
	return;
}

$site = (string) get_site_url();
$site_id = (int) crc32($site);

$notice = '';
$notice_class = 'updated';

 $action = isset($_POST['seojusai_intent_action']) ? sanitize_key((string) wp_unslash($_POST['seojusai_intent_action'])) : '';

if ($action === 'enqueue') {
	check_admin_referer('seojusai_intent_enqueue');

	$queue = new TaskQueue();
	$key = hash('sha256', 'intent_cannibal_audit|' . $site);

	$ok = $queue->enqueue(
		'intent_cannibal_audit',
		[
			'site' => $site,
			'priority' => 'low',
		],
		$key
	);

	if ( $ok ) {
		$notice = esc_html__('Запит поставлено у чергу. Оновіть сторінку через кілька секунд.', 'seojusai');
		$notice_class = 'updated';
	} else {
		$notice = esc_html__('Не вдалося поставити завдання у чергу. Перевірте «Безпечний режим» та системні журнали.', 'seojusai');
		$notice_class = 'notice-error';
	}
}

$repo = new SnapshotRepository();
$latest = $repo->get_latest('intent', $site_id, 1);
$report = [];
$report_at = '';
if ( ! empty($latest) && is_array($latest[0]['data'] ?? null) ) {
	$report = (array) $latest[0]['data'];
	$report_at = (string) ($latest[0]['at'] ?? '');
}

echo '<div class="wrap">';
echo '<h1>' . esc_html__('Інтент і канібалізація', 'seojusai') . '</h1>';

echo '<div class="seojusai-card">';
echo '<p>' . esc_html__('Цей модуль будує звіт на основі останнього знімка Google Search Console (запити × сторінки).', 'seojusai') . '</p>';
echo '<p>' . esc_html__('Для актуальності спершу оновіть «GSC: Аналітика», а потім сформуйте звіт тут.', 'seojusai') . '</p>';

if ( $notice !== '' ) {
	echo '<div class="notice ' . esc_attr($notice_class) . '"><p>' . esc_html($notice) . '</p></div>';
}

echo '<form method="post" style="margin-top:12px;">';
wp_nonce_field('seojusai_intent_enqueue');
echo '<input type="hidden" name="seojusai_intent_action" value="enqueue" />';
echo '<button type="submit" class="button button-primary">' . esc_html__('Сформувати звіт', 'seojusai') . '</button>';
echo '</form>';
echo '</div>';

echo '<div class="seojusai-card" style="margin-top:16px;">';
echo '<h2>' . esc_html__('Останній звіт', 'seojusai') . '</h2>';

if ( empty($report) ) {
	echo '<p>' . esc_html__('Звіт ще не згенеровано або немає «реальності» з GSC.', 'seojusai') . '</p>';
	echo '</div>';
	echo '</div>';
	return;
}

if ( $report_at !== '' ) {
	echo '<p><strong>' . esc_html__('Дата:', 'seojusai') . '</strong> ' . esc_html($report_at) . '</p>';
}

$intent_counts = (array) ($report['intent_counts'] ?? []);
echo '<h3>' . esc_html__('Розподіл інтенцій', 'seojusai') . '</h3>';
echo '<table class="widefat striped"><thead><tr>';
echo '<th>' . esc_html__('Інтенція', 'seojusai') . '</th><th>' . esc_html__('Кількість запитів', 'seojusai') . '</th>';
echo '</tr></thead><tbody>';

$labels = [
	'informational' => esc_html__('Інформаційний', 'seojusai'),
	'commercial' => esc_html__('Комерційний', 'seojusai'),
	'navigational' => esc_html__('Навігаційний', 'seojusai'),
	'local' => esc_html__('Локальний', 'seojusai'),
	'legal_action' => esc_html__('Юридична дія', 'seojusai'),
];

foreach ( $labels as $k => $label ) {
	$val = (int) ($intent_counts[$k] ?? 0);
	echo '<tr><td>' . esc_html($label) . '</td><td>' . esc_html((string) $val) . '</td></tr>';
}
echo '</tbody></table>';

echo '<h3 style="margin-top:18px;">' . esc_html__('Канібалізація запитів', 'seojusai') . '</h3>';
$cannibal = (array) ($report['cannibalization'] ?? []);
if ( empty($cannibal) ) {
	echo '<p>' . esc_html__('Ознак канібалізації не виявлено (за поточними даними).', 'seojusai') . '</p>';
} else {
	echo '<table class="widefat striped"><thead><tr>';
	echo '<th>' . esc_html__('Запит', 'seojusai') . '</th>';
	echo '<th>' . esc_html__('Сторінки', 'seojusai') . '</th>';
	echo '<th>' . esc_html__('Ризик', 'seojusai') . '</th>';
	echo '</tr></thead><tbody>';

	foreach ( $cannibal as $row ) {
		if ( ! is_array($row) ) { continue; }
		$q = (string) ($row['query'] ?? '');
		$pages = (array) ($row['pages'] ?? []);
		$score = (float) ($row['score'] ?? 0);
		$risk = $score >= 0.75 ? esc_html__('Високий', 'seojusai') : ($score >= 0.4 ? esc_html__('Середній', 'seojusai') : esc_html__('Низький', 'seojusai'));

		$page_list = [];
		foreach ( $pages as $p ) {
			if ( ! is_array($p) ) { continue; }
			$url = (string) ($p['page'] ?? '');
			$impr = (float) ($p['impressions'] ?? 0);
			if ( $url === '' ) { continue; }
			$page_list[] = esc_url($url) . ' (' . esc_html((string) (int) $impr) . ')';
		}

		echo '<tr>';
		echo '<td>' . esc_html($q) . '</td>';
		echo '<td>' . wp_kses_post(implode('<br>', $page_list)) . '</td>';
		echo '<td>' . esc_html($risk) . '</td>';
		echo '</tr>';
	}
	echo '</tbody></table>';
}

echo '</div>';
echo '</div>';
