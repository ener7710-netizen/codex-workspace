<?php
declare(strict_types=1);

defined('ABSPATH') || exit;

if (!current_user_can('manage_options')) {
	wp_die(esc_html__('Недостатньо прав доступу.', 'seojusai'));
}

$ver = defined('SEOJUSAI_VERSION') ? SEOJUSAI_VERSION : '1.0.0';

wp_enqueue_style('seojusai-admin-ui', plugins_url('assets/admin/admin-ui.css', SEOJUSAI_FILE), [], $ver);
wp_enqueue_script('seojusai-schema-admin', plugins_url('assets/js/schema.js', SEOJUSAI_FILE), ['wp-api-fetch'], $ver, true);
wp_localize_script('seojusai-schema-admin', 'SEOJusAIAdminSettings', [
	'restUrl' => esc_url_raw(rest_url('seojusai/v1')),
	'nonce'   => wp_create_nonce('wp_rest'),
]);

?>
<div class="wrap seojusai-admin">
	<h1><?php echo esc_html__('Схеми Schema.org', 'seojusai'); ?></h1>

	<div class="seojusai-card">
		<h2 class="seojusai-card__title"><?php echo esc_html__('Попередній перегляд та підтвердження', 'seojusai'); ?></h2>
		<p class="seojusai-muted">
			<?php echo esc_html__('Вставте JSON-LD (@context та @type обовʼязкові) і застосуйте до сторінки. Зміни фіксуються через Snapshot.', 'seojusai'); ?>
		</p>

		<div class="seojusai-grid">
			<div class="seojusai-field">
				<label for="seojusai-schema-post-id"><?php echo esc_html__('ID сторінки/запису', 'seojusai'); ?></label>
				<input id="seojusai-schema-post-id" type="number" min="1" step="1" class="regular-text" placeholder="123" />
			</div>
		</div>

		<div class="seojusai-field">
			<label for="seojusai-schema-json"><?php echo esc_html__('JSON-LD Schema', 'seojusai'); ?></label>
			<textarea id="seojusai-schema-json" class="large-text code" rows="10" placeholder='{"@context":"https://schema.org","@type":"LegalService","name":"..."}'></textarea>
		</div>

		<div class="seojusai-actions">
			<button class="button button-secondary" id="seojusai-schema-preview"><?php echo esc_html__('Перевірити (Preview)', 'seojusai'); ?></button>
			<button class="button button-primary" id="seojusai-schema-apply"><?php echo esc_html__('Підтвердити та застосувати', 'seojusai'); ?></button>
			<span class="seojusai-spinner" id="seojusai-schema-spinner" aria-hidden="true"></span>
		</div>

		<div class="seojusai-notice" id="seojusai-schema-notice" style="display:none"></div>

		<div class="seojusai-card seojusai-card--nested" id="seojusai-schema-result" style="display:none">
			<h3 class="seojusai-card__subtitle"><?php echo esc_html__('Результат', 'seojusai'); ?></h3>
			<pre class="seojusai-pre" id="seojusai-schema-result-pre"></pre>
		</div>
	</div>
</div>
