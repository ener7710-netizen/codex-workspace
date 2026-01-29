<?php
declare(strict_types=1);

namespace SEOJusAI\Core;

use SEOJusAI\Admin\Menu;
use SEOJusAI\Admin\AjaxModules;
use SEOJusAI\Editor\EditorSidebar;
use SEOJusAI\Rest\RestKernel;
use SEOJusAI\Tasks\TaskQueue;
use SEOJusAI\Locks\LockManager;
use SEOJusAI\Modules\AiModule;
use SEOJusAI\Modules\SchemaModule;
use SEOJusAI\Modules\AutopilotModule;
use SEOJusAI\Autopilot\AutopilotLogger;
use SEOJusAI\AIMonitoring\Conversion\ConversionTracker;
use SEOJusAI\Decisions\DecisionOutcomeManager;
use SEOJusAI\Modules\TaskStateModule;
use SEOJusAI\Modules\MetaModule;
use SEOJusAI\Modules\ContentScoreModule;
use SEOJusAI\Modules\SitemapModule;
use SEOJusAI\Modules\RedirectsModule;
use SEOJusAI\Modules\BreadcrumbsModule;
use SEOJusAI\Modules\PagespeedModule;
use SEOJusAI\Modules\IntentModule;
use SEOJusAI\Modules\RobotsModule;
use SEOJusAI\Modules\BulkModule;
use SEOJusAI\Modules\LeadFunnelModule;
use SEOJusAI\Modules\ExperimentsModule;
use SEOJusAI\AI\Billing\CreditManager; // Импорт
use WP_Error;
use SEOJusAI\Capabilities\CapabilityGuard;
use SEOJusAI\Capabilities\CapabilityMap;
use SEOJusAI\Safety\SafeMode;
use SEOJusAI\Snapshots\SnapshotService;
use SEOJusAI\Features\FeatureResolver;
use SEOJusAI\Database\Tables;

defined('ABSPATH') || exit;

final class Plugin {

	private static ?self $instance = null;
	private bool $booted = false;

	private function __construct() {}

	public static function instance(): self {
		return self::$instance ??= (new self())->bootstrap();
	}

	private function bootstrap(): self {

		if ($this->booted) {
			return $this;
		}
		$this->booted = true;

		// 🛑 Emergency OFF
		if (class_exists(EmergencyStop::class)) {
			EmergencyStop::deactivate();
		}

		// 🏁 Feature Flags (safe defaults)
if (class_exists(FeatureResolver::class)) {
	FeatureResolver::ensure_defaults();
}

		// 🗄️ DB schema upgrade (dbDelta is safe on every run, but we gate by version to avoid overhead)
		$target_db = '2026.1.1';
		$cur_db = (string) get_option('seojusai_db_version', '0');
		if (version_compare($cur_db, $target_db, '<')) {
			if (class_exists(Tables::class)) {
				(new Tables())->create();
			}
			update_option('seojusai_db_version', $target_db, false);
		}

// 🧱 Core infrastructure
		if (class_exists(TaskQueue::class)) {
			new TaskQueue();
		}
		if (class_exists(LockManager::class)) {
			new LockManager();
		}


		// 🚀 Kernel + Modules
		add_action('seojusai/kernel/register_modules', function (Kernel $kernel) {

			$module_classes = [
				\SEOJusAI\Modules\AiModule::class,
				\SEOJusAI\Modules\AIRiskFunnelModule::class,
				\SEOJusAI\Modules\SchemaModule::class,
				\SEOJusAI\Modules\AutopilotModule::class,
				\SEOJusAI\Modules\TaskStateModule::class,
				\SEOJusAI\Modules\MetaModule::class,
				\SEOJusAI\Modules\ContentScoreModule::class,
				\SEOJusAI\Modules\SitemapModule::class,
				\SEOJusAI\Modules\RedirectsModule::class,
				\SEOJusAI\Modules\BreadcrumbsModule::class,
				\SEOJusAI\Modules\PagespeedModule::class,
				\SEOJusAI\Modules\Ga4Module::class,
				\SEOJusAI\Modules\GscIngestionModule::class,
				\SEOJusAI\Modules\GeminiAnalyticsModule::class,
				\SEOJusAI\Modules\EeatModule::class,
				\SEOJusAI\Modules\RobotsModule::class,
				\SEOJusAI\Modules\BackgroundModule::class,
				\SEOJusAI\Modules\VectorMemoryModule::class,
				\SEOJusAI\Modules\LearningModule::class,
				\SEOJusAI\Modules\CaseLearningModule::class,
				\SEOJusAI\Modules\BulkModule::class,
				\SEOJusAI\Modules\LeadFunnelModule::class,
				\SEOJusAI\Modules\ExperimentsModule::class,
			];

			foreach ($module_classes as $class) {

				if (!class_exists($class)) {
					do_action('seojusai/module/missing', $class);
					continue;
				}

				try {
					$module = new $class();
					if (method_exists($module, 'register')) {
						$module->register($kernel);
					}
				} catch (\Throwable $e) {
					do_action('seojusai/module/error', $class, $e);
					// Do not break registry: continue with other modules.
					continue;
				}
			}
		});

		Kernel::instance();

		// 🧠 Decision/Outcome ядро
		if (class_exists(DecisionOutcomeManager::class)) {
			DecisionOutcomeManager::register();
		}

		// 📈 AI conversions tracking
		if (class_exists(ConversionTracker::class)) {
			ConversionTracker::register();
		}

		// 🧾 Autopilot journal
		if (class_exists(AutopilotLogger::class)) {
			(new AutopilotLogger())->register();
		}


		// 🔁 Tasks (DB queue + executors)
		if (class_exists('SEOJusAI\\Tasks\\TaskHooks')) {
			\SEOJusAI\Tasks\TaskHooks::register();

		if (class_exists('SEOJusAI\\Core\\AnalysisPersistence')) {
			\SEOJusAI\Core\AnalysisPersistence::register();
		}

		}
		if (class_exists('SEOJusAI\\Tasks\\TaskExecutors')) {
			\SEOJusAI\Tasks\TaskExecutors::register();
		add_filter('seojusai/rollback/last', function (bool $result, int $post_id) {
			$service = new SnapshotService();
			$snapshot_id = $service->repo()->get_latest_post_snapshot_id($post_id);
			if ($snapshot_id <= 0) return false;
			$res = $service->restore_post_snapshot($snapshot_id);
			return !is_wp_error($res);
		}, 10, 2);

		}


		// 🌐 REST
		(new RestKernel($this))->register();

		// 🧭 ADMIN
		if (class_exists(Menu::class)) {
			new Menu();
			if (class_exists('SEOJusAI\\SERP\\SerpAdminActions')) {
				\SEOJusAI\SERP\SerpAdminActions::register();
			}
		}
		if (class_exists(AjaxModules::class)) {
			new AjaxModules();
		}
		// 🔧 Page Actions: apply/rollback via admin AJAX (REST namespace is read-only)
		if (class_exists('SEOJusAI\\Admin\\Ajax\\PageActionsAjax')) {
			new \SEOJusAI\Admin\Ajax\PageActionsAjax();
		}
		if (class_exists('\SEOJusAI\Admin\Metabox')) {
			(new \SEOJusAI\Admin\Metabox())->register();
		}
		if (class_exists(EditorSidebar::class)) {
			(new EditorSidebar())->register();
		}
		// 🧾 List tables (Rank Math-like counters)
		if (class_exists('SEOJusAI\\Admin\\ListColumns\\SeoJusAIColumn')) {
			(new \SEOJusAI\Admin\ListColumns\SeoJusAIColumn())->register();
		}

		// 🧠 E-E-A-T
		if (class_exists(\SEOJusAI\Eeat\EeatMetabox::class)) {
			(new \SEOJusAI\Eeat\EeatMetabox())->register();
		}

// 🛡 Safe Mode controller (user-initiated toggle)
if (class_exists('SEOJusAI\\Safety\\SafeModeController')) {
	(new \SEOJusAI\Safety\SafeModeController())->register();
}

// ⚠️ Conflicts detector & banner
if (class_exists('SEOJusAI\\Governance\\ConflictDetector')) {
	\SEOJusAI\Governance\ConflictDetector::register();
}
		add_action('admin_notices', [$this, 'admin_notices'], 10);

		return $this;
	}

	public function admin_notices(): void {
		if (!is_admin() || !CapabilityGuard::can(CapabilityMap::MANAGE_SETTINGS)) {
			return;
		}

		if (class_exists(EmergencyStop::class) && EmergencyStop::is_active()) {
			echo '<div class="notice notice-error"><p>';
			echo esc_html__('SEOJusAI: АКТИВОВАНО АВАРІЙНУ ЗУПИНКУ.', 'seojusai');
			echo '</p></div>';
		}

		if (class_exists(SafeMode::class) && SafeMode::is_enabled()) {
			echo '<div class="notice notice-warning"><p>';
			echo esc_html__('SEOJusAI: УВІМКНЕНО SAFE MODE (тільки перегляд). Застосування змін заблоковано.', 'seojusai');
			echo '</p></div>';
		}
	}

	public static function error(string $code, string $message, int $status = 400): WP_Error {
		return new WP_Error($code, $message, ['status' => $status]);
	}
}
