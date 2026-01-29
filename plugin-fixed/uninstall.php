<?php
declare(strict_types=1);

/**
 * Файл видалення плагіна.
 * Викликається автоматично, коли користувач натискає "Видалити" в адмінці.
 */

if (!defined('WP_UNINSTALL_PLUGIN')) {
	exit;
}

global $wpdb;

// 1. Видалення всіх таблиць бази даних
$tables = [
	'seojusai_kbe',
	'seojusai_snapshots',
	'seojusai_explanations',
	'seojusai_locks',
	'seojusai_impact',
	'seojusai_pagespeed_history',
	'seojusai_tasks',
	'seojusai_trace',
	'seojusai_redirects',
	'seojusai_404',
	'seojusai_vectors',
	'seojusai_learning',
	'seojusai_knowledge'
];

foreach ($tables as $table) {
	$wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}{$table}");
}

// 2. Видалення всіх налаштувань (Options)
$options = [
	'seojusai_db_version',
	'seojusai_emergency_stop',
	'seojusai_openai_key',
	'seojusai_gemini_key',
	'seojusai_openai_model',
	'seojusai_pagespeed_key',
	'seojusai_serp_key',
	'seojusai_module_settings' // Стан перемикачів у картках
];

foreach ($options as $opt) {
	delete_option($opt);
}

// 3. Очищення кешу (Transients)
delete_transient('seojusai_gsc_token');

// 4. Видалення папки з ключами Google (безпека)
$upload_dir = wp_upload_dir();
$keys_dir = $upload_dir['basedir'] . '/seojusai';

if (is_dir($keys_dir)) {
    // Функція для рекурсивного видалення папки
    $files = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($keys_dir, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );

    foreach ($files as $fileinfo) {
        $todo = ($fileinfo->isDir() ? 'rmdir' : 'unlink');
        // Try to remove file or directory; log errors instead of suppressing
        $path = $fileinfo->getRealPath();
        if ($path !== false && !$todo($path)) {
            if (function_exists('error_log')) {
                error_log('SEOJusAI uninstall: Failed to remove ' . $path);
            }
        }
    }
    if (!rmdir($keys_dir)) {
        if (function_exists('error_log')) {
            error_log('SEOJusAI uninstall: Failed to remove directory ' . $keys_dir);
        }
    }
}
