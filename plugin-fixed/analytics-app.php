<?php
declare(strict_types=1);

defined('ABSPATH') || exit;

if (!current_user_can('manage_options')) {
	wp_die(esc_html__('Недостатньо прав доступу.', 'seojusai'));
}

$tab = isset($_GET['tab']) ? sanitize_key((string) wp_unslash($_GET['tab'])) : 'dashboard';
$allowed = ['dashboard','site','seo','keywords','tracker'];
if (!in_array($tab, $allowed, true)) {
	$tab = 'dashboard';
}

$tabs = [
	'dashboard' => __('Огляд', 'seojusai'),
	'site'      => __('Аналітика сайту', 'seojusai'),
	'seo'       => __('Ефективність SEO', 'seojusai'),
	'keywords'  => __('Ключові слова', 'seojusai'),
	'tracker'   => __('Відстежувач', 'seojusai'),
];


function seojusai_analytics_kpi_cards(array $items): void {
    // $items: [ ['label' => '...', 'metric' => 'ga4.sessions'], ... ]
    echo '<div class="jusai-kpis">';
    foreach ($items as $it) {
        $label  = is_array($it) ? (string)($it['label'] ?? '') : (string)$it;
        $metric = is_array($it) ? (string)($it['metric'] ?? '') : '';
        $attr   = $metric !== '' ? ' data-metric="' . esc_attr($metric) . '"' : '';
        echo '<div class="jusai-kpi"' . $attr . '><div class="kpi-title">' . esc_html($label) . '</div><div class="kpi-num">—</div></div>';
    }
    echo '</div>';
}

function seojusai_analytics_chart_box(string $title): void {
	echo '<div class="jusai-card"><div class="jusai-card-header"><h3>' . esc_html($title) . '</h3><select id="seojusai-analytics-days"><option value="7">7 Days</option><option value="14">14 Days</option><option value="28">28 Days</option><option value="30" selected>30 Days</option><option value="90">90 Days</option></select></div><div class="jusai-chart"></div></div>';
}


function seojusai_analytics_table_box(string $title, array $cols, string $tableKey = ''): void {
    $keyAttr = $tableKey !== '' ? ' data-table="' . esc_attr($tableKey) . '"' : '';
    echo '<div class="jusai-card"' . $keyAttr . '><div class="jusai-card-header"><h3>' . esc_html($title) . '</h3></div>';
    echo '<table class="widefat striped"><thead><tr>';
    foreach ($cols as $c) {
        echo '<th>' . esc_html((string)$c) . '</th>';
    }
    echo '</tr></thead><tbody><tr class="seojusai-empty"><td colspan="' . (int)count($cols) . '">' . esc_html__('Немає даних', 'seojusai') . '</td></tr></tbody></table></div>';
}
?>
<div class="wrap jusai-analytics" data-seojusai-analytics-root="1">
	<h1><?php echo esc_html__('Аналітика', 'seojusai'); ?></h1>

	<div class="notice notice-warning seojusai-analytics-notice" style="display:none"><p></p></div>
	<div class="notice notice-error seojusai-analytics-error" style="display:none"><p></p></div>
	<div class="notice notice-success seojusai-analytics-success" style="display:none"><p></p></div>

	<h2 class="nav-tab-wrapper jusai-tabs">
		<?php foreach ($tabs as $slug => $label): ?>
			<?php $class = ($slug === $tab) ? ' nav-tab-active' : ''; ?>
			<a class="nav-tab<?php echo esc_attr($class); ?>" href="<?php echo esc_url(admin_url('admin.php?page=seojusai-analytics&tab=' . $slug)); ?>">
				<?php echo esc_html($label); ?>
			</a>
		<?php endforeach; ?>
	</h2>

	<?php if ($tab === 'dashboard'): ?>
		<?php seojusai_analytics_kpi_cards([
            ['label' => __('Search Impressions', 'seojusai'), 'metric' => 'gsc.impressions'],
            ['label' => __('Search Clicks', 'seojusai'), 'metric' => 'gsc.clicks'],
            ['label' => __('CTR', 'seojusai'), 'metric' => 'gsc.ctr'],
            ['label' => __('Total Keywords', 'seojusai'), 'metric' => 'gsc.keywords'],
        ]); ?>
		<div class="jusai-grid-2">
			<div class="jusai-card"><h3 style="padding:14px 18px"><?php echo esc_html__('Overall Optimization', 'seojusai'); ?></h3><div class="jusai-donut">0</div></div>
			<?php seojusai_analytics_chart_box(__('Keyword Positions', 'seojusai')); ?>
		</div>
		<?php seojusai_analytics_table_box(__('Top Winning & Losing Keywords', 'seojusai'), [__('Keyword', 'seojusai'), __('Position', 'seojusai'), __('Change', 'seojusai')]); ?>
	<?php endif; ?>

	<?php if ($tab === 'site'): ?>
		<?php seojusai_analytics_kpi_cards([
            ['label' => __('Покази', 'seojusai'), 'metric' => 'gsc.impressions'],
            ['label' => __('Кліки', 'seojusai'), 'metric' => 'gsc.clicks'],
            ['label' => __('CTR', 'seojusai'), 'metric' => 'gsc.ctr'],
            ['label' => __('Сесії', 'seojusai'), 'metric' => 'ga4.sessions'],
        ]); ?>
		<?php seojusai_analytics_chart_box(__('Трафік сайту', 'seojusai')); ?>
		<?php seojusai_analytics_table_box(__('Сторінки', 'seojusai'), [__('Сторінка', 'seojusai'), __('Покази', 'seojusai'), __('Кліки', 'seojusai'), __('CTR', 'seojusai')], 'gsc.top_pages'); ?>
	<?php endif; ?>

	<?php if ($tab === 'seo'): ?>
		<?php seojusai_analytics_kpi_cards([
            ['label' => __('Усього показів', 'seojusai'), 'metric' => 'gsc.impressions'],
            ['label' => __('Усього ключових слів', 'seojusai'), 'metric' => 'gsc.keywords'],
            ['label' => __('Усього натискань', 'seojusai'), 'metric' => 'gsc.clicks'],
            ['label' => __('CTR', 'seojusai'), 'metric' => 'gsc.ctr'],
            ['label' => __('Середня позиція', 'seojusai'), 'metric' => 'gsc.position'],
        ]); ?>
		<?php seojusai_analytics_chart_box(__('SEO Performance', 'seojusai')); ?>
		<?php seojusai_analytics_table_box(__('Top Winning & Losing Posts', 'seojusai'), [__('Сторінка', 'seojusai'), __('Позиція', 'seojusai'), __('Зміна', 'seojusai')]); ?>
	<?php endif; ?>

	<?php if ($tab === 'keywords'): ?>
		<?php seojusai_analytics_kpi_cards([
            ['label' => __('Кращі 3 позиції', 'seojusai'), 'metric' => 'gsc.pos_1_3'],
            ['label' => __('4-10 позицій', 'seojusai'), 'metric' => 'gsc.pos_4_10'],
            ['label' => __('Позиції 10-50', 'seojusai'), 'metric' => 'gsc.pos_11_50'],
            ['label' => __('Позиції 51-100', 'seojusai'), 'metric' => 'gsc.pos_51_100'],
        ]); ?>
		<?php seojusai_analytics_chart_box(__('Розподіл позицій ключових слів', 'seojusai')); ?>
		<?php seojusai_analytics_table_box(__('Решта ключових слів', 'seojusai'), ['#', __('Ключові слова', 'seojusai'), __('Покази', 'seojusai'), __('Кліки', 'seojusai'), __('Позиція', 'seojusai')], 'gsc.top_queries'); ?>

		<div class="jusai-card" id="seojusai-serp-overlay" style="display:none">
			<div class="jusai-card-header"><h3><?php echo esc_html__('SERP (конкуренти по запиту)', 'seojusai'); ?></h3></div>
			<div class="seojusai-serp-overlay__body" style="padding: 12px 16px">
				<p class="seojusai-serp-overlay__hint" style="margin-top:0"><?php echo esc_html__('Натисніть на ключове слово в таблиці, щоб завантажити SERP.', 'seojusai'); ?></p>
				<div class="seojusai-serp-overlay__keyword" style="font-weight:600"></div>
				<ol class="seojusai-serp-overlay__list" style="margin: 10px 0 0 18px"></ol>
			</div>
		</div>
	<?php endif; ?>

	<?php if ($tab === 'tracker'): ?>
		<div class="jusai-card jusai-empty">
			<h3><?php echo esc_html__('Відстеження ключових слів', 'seojusai'); ?></h3>
			<p><?php echo esc_html__('Дані зʼявляться після підключення Search Console.', 'seojusai'); ?></p>
			<a class="button button-primary" href="<?php echo esc_url(admin_url('admin.php?page=seojusai-settings')); ?>"><?php echo esc_html__('Підключити', 'seojusai'); ?></a>
		</div>
	<?php endif; ?>

</div>
