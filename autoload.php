<?php
declare(strict_types=1);

defined('ABSPATH') || exit;

spl_autoload_register(static function (string $class): void {

	if (strpos($class, 'SEOJusAI\\') !== 0) {
		return;
	}

	$path = str_replace(
		['SEOJusAI\\', '\\'],
		['', DIRECTORY_SEPARATOR],
		$class
	);

	$file = __DIR__ . '/' . $path . '.php';

	if (is_file($file)) {
		require_once $file;
	}
});
