<?php
declare(strict_types=1);

defined('ABSPATH') || exit;

if (!current_user_can('manage_options')) return;

$refresh = isset($_GET['seojusai_refresh']) ? sanitize_text_field((string) wp_unslash($_GET['seojusai_refresh'])) : '';
if ($refresh === '1' && isset($_GET['_wpnonce']) && wp_verify_nonce((string) wp_unslash($_GET['_wpnonce']), 'seojusai_conflicts_refresh')) {
	if (class_exists('SEOJusAI\\Governance\\ConflictDetector')) {
		\SEOJusAI\Governance\ConflictDetector::store_state();
	}
}

// Ensure plugin functions exist
if (!function_exists('is_plugin_active')) {
	require_once ABSPATH . 'wp-admin/includes/plugin.php';
}

$active = [
	'rank_math' => defined('RANK_MATH_VERSION') || class_exists('RankMath\\Admin') || is_plugin_active('seo-by-rank-math/rank-math.php'),
	'yoast'     => defined('WPSEO_VERSION') || class_exists('WPSEO_Frontend') || is_plugin_active('wordpress-seo/wp-seo.php'),
	'aioseo'    => defined('AIOSEO_VERSION') || class_exists('AIOSEO\\Plugin') || is_plugin_active('all-in-one-seo-pack/all_in_one_seo_pack.php') || is_plugin_active('all-in-one-seo-pack-pro/all_in_one_seo_pack.php'),
];

if (class_exists('SEOJusAI\\Governance\\ConflictDetector')) {
	$state = \SEOJusAI\Governance\ConflictDetector::state();
	if (is_array($state) && isset($state['active']) && is_array($state['active'])) {
		$active = $state['active'];
	}
	if (is_array($state) && isset($state['controller'])) {
		$controller = (string) $state['controller'];
	}
}

$controller = '—';
if ($active['rank_math']) { $controller = 'Rank Math'; }
elseif ($active['yoast']) { $controller = 'Yoast SEO'; }
elseif ($active['aioseo']) { $controller = 'AIOSEO'; }

$zones = [
	[
		'slug' => 'schema',
		'title' => __('Schema', 'seojusai'),
		'desc'  => __('Розмітка структурованих даних (JSON‑LD).', 'seojusai'),
	],
	[
		'slug' => 'sitemap',
		'title' => __('Sitemap', 'seojusai'),
		'desc'  => __('XML‑карти сайту та індекси.', 'seojusai'),
	],
	[
		'slug' => 'redirects',
		'title' => __('Redirects', 'seojusai'),
		'desc'  => __('301/302 редиректи та правила переадресації.', 'seojusai'),
	],
];

function seojusai_severity(string $controller): array {
	if ($controller === '—') return ['ok', __('OK', 'seojusai'), __('Конфліктів не виявлено', 'seojusai')];
	return ['warning', __('Warning', 'seojusai'), sprintf(__('Виявлено активний SEO‑плагін: %s. Рекомендуємо уникати дублювання керування.', 'seojusai'), $controller)];
}

[$sev, $sev_label, $sev_hint] = seojusai_severity($controller);

?>
<div class="wrap seojusai-wrap">
	<h1><?php echo esc_html__('Конфлікти керування', 'seojusai'); ?></h1>

	<div style="background:#fff;border:1px solid #e5e5e5;border-radius:12px;padding:16px;max-width:980px;">
		<p style="margin-top:0;">
			<strong><?php echo esc_html__('Статус:', 'seojusai'); ?></strong>
			<span style="display:inline-block;margin-left:8px;padding:2px 8px;border-radius:999px;border:1px solid #ddd;">
				<?php echo esc_html($sev_label); ?>
			</span>
		</p>
		<p style="margin:0;color:#444;"><?php echo esc_html($sev_hint); ?></p>
		<p style="margin:12px 0 0;"><a class="button" href="<?php echo esc_url(wp_nonce_url(admin_url('admin.php?page=seojusai-conflicts&seojusai_refresh=1'), 'seojusai_conflicts_refresh')); ?>"><?php echo esc_html__('Оновити перевірку', 'seojusai'); ?></a></p>

		<?php if ($controller !== '—'): ?>
			<hr style="margin:16px 0;">
			<p style="margin:0;color:#444;">
				<?php echo esc_html__('SEOJusAI не вимикає інші плагіни автоматично. Для стабільності оберіть, хто керує кожною зоною (Schema/Sitemap/Redirects).', 'seojusai'); ?>
			</p>
		<?php endif; ?>
	</div>

	<h2 style="margin-top:20px;"><?php echo esc_html__('Зони', 'seojusai'); ?></h2>

	<table class="widefat striped" style="max-width:980px;">
		<thead>
			<tr>
				<th><?php echo esc_html__('Зона', 'seojusai'); ?></th>
				<th><?php echo esc_html__('Поточний контролер', 'seojusai'); ?></th>
				<th><?php echo esc_html__('Рекомендація', 'seojusai'); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php foreach ($zones as $z): ?>
				<tr>
					<td>
						<strong><?php echo esc_html($z['title']); ?></strong><br>
						<span style="color:#666;"><?php echo esc_html($z['desc']); ?></span>
					</td>
					<td><?php echo esc_html($controller); ?></td>
					<td>
						<?php if ($controller === '—'): ?>
							<?php echo esc_html__('Можна керувати через SEOJusAI.', 'seojusai'); ?>
						<?php else: ?>
							<?php echo esc_html__('Рекомендуємо залишити цю зону за одним плагіном. Якщо обираєте SEOJusAI — вимкніть відповідний модуль в іншому плагіні.', 'seojusai'); ?>
						<?php endif; ?>
					</td>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>

	<div style="max-width:980px;margin-top:16px;color:#555;font-size:13px;">
		<p style="margin:0;">
			<?php echo esc_html__('Порада: якщо у вас активний Rank Math/Yoast/AIOSEO, починайте з режиму SAFE та застосовуйте лише low‑risk зміни, доки не узгодите керування зонами.', 'seojusai'); ?>
		</p>
	</div>
</div>
