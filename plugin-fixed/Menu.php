<?php
declare(strict_types=1);

namespace SEOJusAI\Admin;

use SEOJusAI\Admin\Tasks\TasksPage;

defined('ABSPATH') || exit;

final class Menu {

	public function __construct() {
		add_action('admin_menu', [$this, 'register_menu']);
		add_action('admin_enqueue_scripts', [$this, 'enqueue_assets']);
	}

	public function register_menu(): void {

		$cap = 'manage_options';
		$icon = 'dashicons-chart-area';

        add_menu_page(
			'SEOJusAI',
			'SEOJusAI',
			$cap,
			'seojusai',
			[$this, 'render_dashboard'],
			$icon,
			30
		);

        // Clients page remains a standalone submenu item.
        add_submenu_page(
            'seojusai',
            'Клієнти',
            'Клієнти',
            'manage_options',
            'seojusai_clients',
            function () {
                include __DIR__ . '/pages/clients.php';
            }
        );

        // Top-level dashboard mirrors main menu.
        add_submenu_page('seojusai', __('Огляд', 'seojusai'), __('Огляд', 'seojusai'), $cap, 'seojusai', [$this, 'render_dashboard']);

        // Decision center is still a separate page.
        add_submenu_page('seojusai', __('Центр рішень', 'seojusai'), __('Центр рішень', 'seojusai'), $cap, 'seojusai_decisions', function () {
            include __DIR__ . '/pages/decision-center.php';
        });

        // Unified Audit page (Stage 2)
        add_submenu_page('seojusai', __('Аудит', 'seojusai'), __('Аудит', 'seojusai'), $cap, 'seojusai-audit', [$this, 'render_audit']);

        // Unified Execution page (Stage 2) combining Autopilot and Queue
        add_submenu_page('seojusai', __('Виконання', 'seojusai'), __('Виконання', 'seojusai'), $cap, 'seojusai-execution', [$this, 'render_execution']);

        // Unified Analytics page with tabs for general and posts (Stage 2)
        add_submenu_page('seojusai', __('Аналітика', 'seojusai'), __('Аналітика', 'seojusai'), $cap, 'seojusai-analytics', [$this, 'render_analytics_tabs']);

        // Keep other functional pages as individual menu items.
        add_submenu_page('seojusai', __('Редиректи', 'seojusai'), __('Редиректи', 'seojusai'), $cap, 'seojusai-redirects', [$this, 'render_redirects']);
        add_submenu_page('seojusai', __('Schema', 'seojusai'), __('Schema', 'seojusai'), $cap, 'seojusai-schema', [$this, 'render_schema']);
        add_submenu_page('seojusai', __('AI та Дані', 'seojusai'), __('AI та Дані', 'seojusai'), $cap, 'seojusai-ai-data', [$this, 'render_ai_settings']);
        add_submenu_page('seojusai', __('Стратегія', 'seojusai'), __('Стратегія', 'seojusai'), $cap, 'seojusai-strategy', [$this, 'render_strategy']);
        add_submenu_page('seojusai', __('Керування', 'seojusai'), __('Керування', 'seojusai'), $cap, 'seojusai-governance', [$this, 'render_governance']);

	}


	/* ==========================================================
	 * RENDERERS
	 * ========================================================== */

	public function render_dashboard(): void {
		$this->safe_require('Admin/pages/dashboard.php');
	}

	public function render_decisions(): void {
		$this->safe_require('Admin/pages/decisions.php');
	}

	public function render_ai_conversions(): void {
		$this->safe_require('Admin/pages/ai-conversions.php');
	}

	public function render_ai_settings(): void {
		$this->safe_require('Admin/pages/ai-settings.php');
	}

	public function render_lead_funnel(): void {
		$this->safe_require('Admin/pages/lead-funnel.php');
	}

	public function render_governance(): void {
		$this->safe_require('Admin/pages/governance.php');
	}

	public function render_conflicts(): void {
		$this->safe_require('Admin/pages/conflicts.php');
	}

	public function render_market_signals(): void {
		$this->safe_require('Admin/pages/market-signals.php');
	}

	public function render_experiments(): void {
		$this->safe_require('Admin/pages/experiments.php');
	}




	public function render_modules(): void {
		$this->safe_require('Admin/pages/modules.php');
	}

	public function render_tasks(): void {
		if (class_exists(TasksPage::class)) {
			(new TasksPage())->render();
			return;
		}

		echo '<div class="notice notice-error"><p>';
		echo esc_html__('SEOJusAI: Модуль задач не завантажено.', 'seojusai');
		echo '</p></div>';
	}

	/* ==========================================================
	 * HELPERS
	 * ========================================================== */

	

	public function render_opportunity(): void {
		$this->safe_require('Admin/pages/opportunity.php');
	}

	public function render_sitemap(): void {
		$this->safe_require('Admin/pages/sitemap.php');
	}

	public function render_redirects(): void {
		$this->safe_require('Admin/pages/redirects.php');
	}

private function safe_require(string $relative_path): void {
		// Використовуємо константу SEOJUSAI_INC, яку ми визначили в головному файлі
		$file = defined('SEOJUSAI_INC')
			? SEOJUSAI_INC . $relative_path
			: plugin_dir_path(__DIR__) . $relative_path;

		if (file_exists($file) && is_readable($file)) {
			require_once $file;
		} else {
			echo '<div class="notice notice-error"><p>';
			echo esc_html__('Помилка: Файл не знайдено за шляхом: ', 'seojusai');
			echo esc_html($file);
			echo '</p></div>';
		}
	}

	// render_page_analysis removed: page analysis is shown in list-table badge and editor sidebar.

	public function enqueue_assets(string $hook): void {
	// Load base admin UI styles only on our plugin screens.
	if (strpos($hook, 'seojusai') === false) {
		return;
	}

	$ver = defined('SEOJUSAI_VERSION') ? SEOJUSAI_VERSION : '1.0.0';

	wp_enqueue_style(
		'seojusai-admin',
		plugins_url('assets/admin/admin-ui.css', SEOJUSAI_FILE),
		[],
		$ver
	);

	// Rank Math-like pattern: enqueue Analytics assets ONLY on Analytics screen.
    $is_analytics = ($hook === 'seojusai_page_seojusai-analytics')
        || ($hook === 'toplevel_page_seojusai-analytics')
        || (function () {
            // sanitize `page` param from $_GET to avoid unslashed and unsanitized comparisons
            $page = isset($_GET['page']) ? sanitize_key((string) wp_unslash($_GET['page'])) : '';
            return $page === 'seojusai-analytics';
        })();

	if (!$is_analytics) {
		return;
	}

	// Styles for Analytics UI shell (PHP-rendered).
	wp_enqueue_style('wp-components');
	$analytics_css = plugins_url('assets/admin/analytics.css', SEOJUSAI_FILE);
	wp_enqueue_style('seojusai-analytics', $analytics_css, ['seojusai-admin', 'wp-components'], $ver);

// Hydrator: fills KPI cards and tables with GA4/GSC stats via REST.
wp_enqueue_script(
    'seojusai-analytics-hydrate',
    plugins_url('assets/js/analytics-hydrate.js', SEOJUSAI_FILE),
    ['wp-api-fetch','wp-i18n','lodash'],
    $ver,
    true
);
wp_localize_script('seojusai-analytics-hydrate', 'SEOJusAIAnalyticsApp', [
    'restRoot' => '/wp-json/seojusai/v1',
    'nonce' => wp_create_nonce('wp_rest'),    'homeUrl' => home_url('/'),
    'siteHost' => (string) wp_parse_url(home_url('/'), PHP_URL_HOST),
    'gscSite' => (string) get_option('seojusai_gsc_site', ''),
]);


	// SPA (React) assets are intentionally NOT enqueued here.
	// This page now renders a stable PHP UI shell (appearance like your UI zip).
}



public function render_serp(): void {
    $file = SEOJUSAI_PATH . 'src/Admin/pages/module-serp.php';
    if (is_readable($file)) {
        require $file;
        return;
    }
    echo '<div class="wrap"><h1>' . esc_html__('SERP', 'seojusai') . '</h1></div>';
}

public function render_autopilot(): void {
    $file = SEOJUSAI_PATH . 'src/Admin/pages/autopilot.php';
    if (is_readable($file)) {
        require $file;
        return;
    }
    echo '<div class="wrap"><h1>' . esc_html__('Автопілот', 'seojusai') . '</h1></div>';
}

public function render_strategy(): void {
    $file = SEOJUSAI_PATH . 'src/Admin/pages/strategy.php';
    if (is_readable($file)) {
        require $file;
        return;
    }
    echo '<div class="wrap"><h1>' . esc_html__('Стратегія', 'seojusai') . '</h1></div>';
}

public function render_system_health(): void {
    $file = SEOJUSAI_PATH . 'src/Admin/pages/system-health.php';
    if (is_readable($file)) {
        require $file;
        return;
    }
    echo '<div class="wrap"><h1>' . esc_html__('Система', 'seojusai') . '</h1></div>';
}

public function render_task_queue(): void {
    $file = SEOJUSAI_PATH . 'src/Admin/pages/task-queue.php';
    if (is_readable($file)) {
        require $file;
        return;
    }
    echo '<div class="wrap"><h1>' . esc_html__('Черга завдань', 'seojusai') . '</h1></div>';
}

public function render_explain_director(): void {
    $file = SEOJUSAI_PATH . 'src/Admin/pages/explain-director.php';
    if (is_readable($file)) {
        require $file;
        return;
    }
    echo '<div class="wrap"><h1>' . esc_html__('Explain', 'seojusai') . '</h1></div>';
}

public function render_google_search_analytics(): void {
    $file = SEOJUSAI_PATH . 'src/Admin/pages/google-search-analytics.php';
    if (is_readable($file)) {
        require $file;
        return;
    }
    echo '<div class="wrap"><h1>' . esc_html__('Google Search Console', 'seojusai') . '</h1></div>';
}

public function render_google_search_history(): void {
    $file = SEOJUSAI_PATH . 'src/Admin/pages/google-search-history.php';
    if (is_readable($file)) {
        require $file;
        return;
    }
    echo '<div class="wrap"><h1>' . esc_html__('Google Search Console', 'seojusai') . '</h1></div>';
}



public function render_schema(): void {
	require SEOJUSAI_PATH . 'src/Admin/pages/schema.php';
}


public function render_internal_links(): void {
	require SEOJUSAI_PATH . 'src/Admin/pages/internal-links.php';
}

	public function render_vector_memory(): void {
		$this->safe_require('Admin/pages/vector-memory.php');
	}

	public function render_learning(): void {
		$this->safe_require('Admin/pages/learning.php');
	}

	public function render_intent_cannibalization(): void {
		$this->safe_require('Admin/pages/intent-cannibalization.php');
	}

    
	public function render_analytics(): void {
		$this->safe_require('Admin/pages/analytics-app.php');
	}

	public function render_analytics_posts(): void {
		$this->safe_require('Admin/pages/analytics-posts.php');
	}

    /*
     * Stage 2: Unified Audit renderer with tabs
     *
     * This method consolidates several audit-related pages into a single entry with
     * a tabbed navigation interface. It does not modify business logic; it simply
     * switches between existing renderers based on the selected tab.
     */
    public function render_audit(): void {
        if (!current_user_can('manage_options')) {
            wp_die(__('У вас немає доступу до цієї сторінки.', 'seojusai'), 403);
        }

        // Determine current tab, defaulting to 'priorities'.
        $tab = isset($_GET['tab']) ? sanitize_key((string) wp_unslash($_GET['tab'])) : 'priorities';
        $tabs = [
            'priorities' => __('Пріоритети', 'seojusai'),
            'intent'     => __('Інтент', 'seojusai'),
            'conflicts'  => __('Конфлікти', 'seojusai'),
            'serp'       => __('SERP / Конкуренти', 'seojusai'),
            'links'      => __('Внутрішні посилання', 'seojusai'),
            'sitemap'    => __('Карта сайту', 'seojusai'),
        ];

        echo '<div class="wrap">';
        echo '<h1>' . esc_html__('Аудит', 'seojusai') . '</h1>';
        echo '<h2 class="nav-tab-wrapper">';
        foreach ($tabs as $key => $label) {
            $active = ($tab === $key) ? ' nav-tab-active' : '';
            $url    = esc_url(add_query_arg(['page' => 'seojusai-audit', 'tab' => $key], admin_url('admin.php')));
            printf('<a href="%s" class="nav-tab%s">%s</a>', $url, esc_attr($active), esc_html($label));
        }
        echo '</h2>';

        // Render the selected tab content
        switch ($tab) {
            case 'intent':
                $this->render_intent_cannibalization();
                break;
            case 'conflicts':
                $this->render_conflicts();
                break;
            case 'serp':
                $this->render_serp();
                break;
            case 'links':
                $this->render_internal_links();
                break;
            case 'sitemap':
                $this->render_sitemap();
                break;
            case 'priorities':
            default:
                $this->render_opportunity();
                break;
        }

        echo '</div>';
    }

    /*
     * Stage 2: Unified Execution renderer with tabs (Autopilot + Queue)
     */
    public function render_execution(): void {
        if (!current_user_can('manage_options')) {
            wp_die(__('У вас немає доступу до цієї сторінки.', 'seojusai'), 403);
        }
        $tab = isset($_GET['tab']) ? sanitize_key((string) wp_unslash($_GET['tab'])) : 'autopilot';
        $tabs = [
            'autopilot' => __('Автопілот', 'seojusai'),
            'queue'     => __('Черга', 'seojusai'),
        ];
        echo '<div class="wrap">';
        echo '<h1>' . esc_html__('Виконання', 'seojusai') . '</h1>';
        echo '<h2 class="nav-tab-wrapper">';
        foreach ($tabs as $key => $label) {
            $active = ($tab === $key) ? ' nav-tab-active' : '';
            $url    = esc_url(add_query_arg(['page' => 'seojusai-execution', 'tab' => $key], admin_url('admin.php')));
            printf('<a href="%s" class="nav-tab%s">%s</a>', $url, esc_attr($active), esc_html($label));
        }
        echo '</h2>';
        switch ($tab) {
            case 'queue':
                // Use safe_require to load queue monitor; unify with existing page.
                $this->safe_require('Admin/pages/queue-monitor.php');
                break;
            case 'autopilot':
            default:
                $this->render_autopilot();
                break;
        }
        echo '</div>';
    }

    /*
     * Stage 2: Unified Analytics renderer with tabs (general + posts)
     */
    public function render_analytics_tabs(): void {
        if (!current_user_can('manage_options')) {
            wp_die(__('У вас немає доступу до цієї сторінки.', 'seojusai'), 403);
        }
        $tab = isset($_GET['tab']) ? sanitize_key((string) wp_unslash($_GET['tab'])) : 'general';
        $tabs = [
            'general' => __('Загальна', 'seojusai'),
            'posts'   => __('По сторінках', 'seojusai'),
        ];
        echo '<div class="wrap">';
        echo '<h1>' . esc_html__('Аналітика', 'seojusai') . '</h1>';
        echo '<h2 class="nav-tab-wrapper">';
        foreach ($tabs as $key => $label) {
            $active = ($tab === $key) ? ' nav-tab-active' : '';
            $url    = esc_url(add_query_arg(['page' => 'seojusai-analytics', 'tab' => $key], admin_url('admin.php')));
            printf('<a href="%s" class="nav-tab%s">%s</a>', $url, esc_attr($active), esc_html($label));
        }
        echo '</h2>';
        switch ($tab) {
            case 'posts':
                $this->render_analytics_posts();
                break;
            case 'general':
            default:
                $this->render_analytics();
                break;
        }
        echo '</div>';
    }

}
