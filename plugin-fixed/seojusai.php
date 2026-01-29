<?php
/**
 * Plugin Name: SEOJusAI Autopilot
 * Plugin URI:  https://example.com/seojusai
 * Description: AI-автопілот для юридичного SEO. Аналіз, перелінковка та Schema.org для юристів.
 * Version:     2.5.3
 * Author:      SEOJusAI Team
 * License:     GPL-2.0+
 * Text Domain: seojusai
 * Domain Path: /languages
 */

declare(strict_types=1);

namespace SEOJusAI;

defined('ABSPATH') || exit;



// Load translations early enough to avoid WP 6.7+ _load_textdomain_just_in_time notices.
\add_action('plugins_loaded', static function (): void {
	if (\function_exists('load_plugin_textdomain')) {
		\load_plugin_textdomain(
			'seojusai',
			false,
			\dirname(\plugin_basename(__FILE__)) . '/languages'
		);
	}
}, 1);

/* -------------------------------------------------------------------------
 * 1. КОНСТАНТИ
 * ---------------------------------------------------------------------- */

// Keep the internal version in sync with the header above.  When bumping
// the version in the plugin header make sure to update this constant
// accordingly.  The DB/migration logic later in this file relies on
// this value instead of hard coded strings.
define('SEOJUSAI_VERSION', '2.5.3');
define('SEOJUSAI_FILE', __FILE__);
define('SEOJUSAI_PATH', plugin_dir_path(__FILE__));
define('SEOJUSAI_URL', plugin_dir_url(__FILE__));
define('SEOJUSAI_SRC', SEOJUSAI_PATH . 'src/');
require_once SEOJUSAI_SRC . 'Utils/PreCheck.php';
// Backward compat (legacy constant name)
define('SEOJUSAI_INC', SEOJUSAI_SRC);
// Legacy folder path (should be unused)
define('SEOJUSAI_LEGACY_INC', SEOJUSAI_PATH . 'includes/');

/**
 * Завантаження перекладів (після init, щоб уникнути notice у WP 6.7+).
 */
function seojusai_load_textdomain(): void {
	load_plugin_textdomain('seojusai', false, dirname(plugin_basename(__FILE__)) . '/languages');
}
add_action('init', __NAMESPACE__ . '\seojusai_load_textdomain');

/* -------------------------------------------------------------------------
 * 2. PSR-4 AUTOLOADER
 * ---------------------------------------------------------------------- */

spl_autoload_register(function ($class) {

	$prefix   = 'SEOJusAI\\';
	$base_dir = SEOJUSAI_SRC;

	$len = strlen($prefix);
	if (strncmp($prefix, $class, $len) !== 0) {
		return;
	}

	$relative_class = substr($class, $len);
	$file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';

	if (file_exists($file)) {
		require_once $file;
	}
});

/* -------------------------------------------------------------------------
 * 3. АКТИВАЦІЯ ПЛАГІНА
 * ---------------------------------------------------------------------- */

register_activation_hook(__FILE__, function () {



// Prevent any accidental output during activation (avoids 'unexpected output' / headers warnings).
$__seojusai_ob_level = ob_get_level();
ob_start();
// 0) Capabilities
	if (class_exists('\\SEOJusAI\\Capabilities\\RoleInstaller')) {
		\SEOJusAI\Capabilities\RoleInstaller::install();
	}


	// 1) Інсталятор (БД + планувальник)
	if (class_exists('\\SEOJusAI\\Installer')) {
		\SEOJusAI\Installer::install();
	}

    // 2) Активатор (кредити/прапори/дефолти)
    if (class_exists('\\SEOJusAI\\Core\\Activator')) {
        \SEOJusAI\Core\Activator::activate();
    }

    /*
     * 3) Governance flags for autonomous execution
     *
     * These flags are intentionally stored with a default of `false` so that
     * autonomous execution does not accidentally enable itself on first run.
     * Do not wrap these option checks inside the Activator class check; they
     * should run independently of whether the Activator class exists.
     */
    if (get_option('seojusai_execution_enabled', null) === null) {
        add_option('seojusai_execution_enabled', false, '', false);
    }
    if (get_option('seojusai_learning_enabled', null) === null) {
        add_option('seojusai_learning_enabled', false, '', false);
    }

    // 4) Autopilot tick scheduling (every 5 minutes)
    // Always schedule the tick regardless of the presence of the Activator
    // class so that the analysis loop can fire.  If the event is already
    // scheduled WordPress will no-op.  Use a short delay from now to
    // ensure the schedule is created after activation completes.
    if (!wp_next_scheduled('seojusai_autopilot_tick')) {
        wp_schedule_event(time() + 60, 'seojusai_5min', 'seojusai_autopilot_tick');
    }


$__seojusai_output = ob_get_clean();
if ($__seojusai_output !== '') {
	// Persist for debugging without breaking activation.
	$upload_dir = wp_upload_dir(null, false);
	$log_dir = trailingslashit($upload_dir['basedir']) . 'seojusai/logs';
	if (!is_dir($log_dir)) {
		wp_mkdir_p($log_dir);
	}
    // Write activation output to a log file; suppressing errors hides real issues
    file_put_contents(trailingslashit($log_dir) . 'activation-output.log', $__seojusai_output);
}
// Restore previous output buffering state if needed.
    while (ob_get_level() > $__seojusai_ob_level) {
        ob_end_clean();
    }

});

/* -------------------------------------------------------------------------
 * 3b. ДЕАКТИВАЦІЯ ПЛАГІНА
 * ---------------------------------------------------------------------- */

register_deactivation_hook(__FILE__, function () {
	// Clear scheduled autopilot tick
	wp_clear_scheduled_hook('seojusai_autopilot_tick');
});



/* -------------------------------------------------------------------------
 * 4. BOOTSTRAP
 * ---------------------------------------------------------------------- */

add_filter('cron_schedules', function (array $schedules): array {
	if (!isset($schedules['seojusai_5min'])) {
		$schedules['seojusai_5min'] = [
			'interval' => 300,
			'display'  => \SEOJusAI\Core\I18n::t('SEOJusAI: кожні 5 хвилин'),
		];
	}
	return $schedules;
}, 20);

// Autopilot autonomous tick (ANALYSIS-only). Does nothing unless explicitly enabled.
add_action('seojusai_autopilot_tick', function () {
	if (class_exists('\\SEOJusAI\\Autopilot\\AutopilotExecutionLoop')) {
		(new \SEOJusAI\Autopilot\AutopilotExecutionLoop())->run();
	}
});


add_action('admin_init', function () {
	// Ensure caps installed after updates
	if (!get_option('seojusai_caps_installed')) {
		if (class_exists('\\SEOJusAI\\Capabilities\\RoleInstaller')) {
			\SEOJusAI\Capabilities\RoleInstaller::install();
			update_option('seojusai_caps_installed', 1, false);
		}
	}
}, 1);

add_action('plugins_loaded', function () {

	// 🔧 Upgrade: ensure DB schema stays up to date (dbDelta is idempotent)
	$ver = (string) get_option('seojusai_plugin_version', '');
	// Use the plugin version constant for DB migrations.  This ensures the
	// stored version tracks the actual plugin version and avoids leaving the
	// schema outdated when bumping versions without updating this string.
	$current = SEOJUSAI_VERSION;
	if ($ver !== $current) {
		if (class_exists('\\SEOJusAI\\Database\\Tables')) {
			(new \SEOJusAI\Database\Tables())->create();
		}
		update_option('seojusai_plugin_version', $current, false);
	}

	// 🔧 Self-heal: create missing critical tables even if version option is already set
	global $wpdb;
	$kbe_like = $wpdb->esc_like($wpdb->prefix . 'seojusai_kbe');
	$kbe_exists = (string) $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $kbe_like));
	if (!$kbe_exists) {
		if (class_exists('\\SEOJusAI\\Database\\Tables')) {
			(new \SEOJusAI\Database\Tables())->create();
		}
	}

	// 🚀 Запуск ядра
	if (class_exists('\\SEOJusAI\\Core\\Plugin')) {
		\SEOJusAI\Core\Plugin::instance();
	}

}, 5);

/**
 * i18n (WP 6.7+ вимагає init або пізніше).
 */
add_action('init', function () {

	if (function_exists('load_plugin_textdomain')) {
		load_plugin_textdomain(
			'seojusai',
			false,
			dirname(plugin_basename(__FILE__)) . '/languages'
		);
	}

}, 5);
