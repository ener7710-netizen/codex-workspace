<?php
declare(strict_types=1);

defined('ABSPATH') || exit;

if (!current_user_can('manage_options')) {
	wp_die(esc_html__('Недостатньо прав доступу.', 'seojusai'));
}

$ver = defined('SEOJUSAI_VERSION') ? SEOJUSAI_VERSION : '1.0.0';

wp_enqueue_style('seojusai-admin-ui', plugins_url('assets/admin/admin-ui.css', SEOJUSAI_FILE), [], $ver);
wp_enqueue_script('seojusai-internal-links', plugins_url('assets/js/internal-links.js', SEOJUSAI_FILE), ['wp-api-fetch'], $ver, true);
wp_localize_script('seojusai-internal-links', 'SEOJusAIAdminSettings', [
	'restUrl' => esc_url_raw(rest_url('seojusai/v1')),
	'nonce'   => wp_create_nonce('wp_rest'),
]);

?>
<div class="wrap seojusai-admin">
	<h1><?php echo esc_html__('Внутрішні посилання', 'seojusai'); ?></h1>

	<div class="seojusai-card">
		<h2 class="seojusai-card__title"><?php echo esc_html__('Сканування та підказки', 'seojusai'); ?></h2>
		<p class="seojusai-muted">
			<?php echo esc_html__('Запустіть фонове сканування, щоб оновити сигнали внутрішньої перелінковки. Результат показується як проблеми та рекомендації у списку сторінок і в редакторі.', 'seojusai'); ?>
		</p>

		<div class="seojusai-grid">
			<div class="seojusai-field">
				<label for="seojusai-il-post-id"><?php echo esc_html__('ID сторінки/запису (необовʼязково)', 'seojusai'); ?></label>
				<input id="seojusai-il-post-id" type="number" min="0" step="1" class="regular-text" placeholder="<?php echo esc_attr__('0 = пакетно', 'seojusai'); ?>" />
			</div>
			<div class="seojusai-field">
				<label for="seojusai-il-limit"><?php echo esc_html__('Ліміт пакетної постановки', 'seojusai'); ?></label>
				<input id="seojusai-il-limit" type="number" min="1" step="1" class="small-text" value="50" />
			</div>
		</div>

		<div class="seojusai-actions">
			<button class="button button-primary" id="seojusai-il-scan"><?php echo esc_html__('Запустити сканування', 'seojusai'); ?></button>
			<button class="button" id="seojusai-il-check"><?php echo esc_html__('Показати проблеми для ID', 'seojusai'); ?></button>
			<span class="seojusai-spinner" id="seojusai-il-spinner" aria-hidden="true"></span>
		</div>

		<div class="seojusai-notice" id="seojusai-il-notice" style="display:none"></div>

		<div class="seojusai-card seojusai-card--nested" id="seojusai-il-result" style="display:none">
			<h3 class="seojusai-card__subtitle"><?php echo esc_html__('Поточні проблеми', 'seojusai'); ?></h3>
			<div id="seojusai-il-issues"></div>
		</div>
	</div>
</div>
