<?php
declare(strict_types=1);

defined('ABSPATH') || exit;

if (!current_user_can('manage_options')) {
	wp_die(esc_html__('Недостатньо прав доступу.', 'seojusai'), 403);
}

$notice = isset($_GET['seojusai_notice']) ? sanitize_key((string) wp_unslash($_GET['seojusai_notice'])) : '';
if ($notice === 'audit_enqueued') {
	echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Аудит сторінки заплановано в черзі. План сформується після завершення завдання.', 'seojusai') . '</p></div>';
} elseif ($notice === 'audit_failed') {
	echo '<div class="notice notice-error is-dismissible"><p>' . esc_html__('Не вдалося запланувати аудит. Перевірте чергу та повторіть спробу.', 'seojusai') . '</p></div>';
} elseif ($notice === 'audit_missing_post') {
	echo '<div class="notice notice-warning is-dismissible"><p>' . esc_html__('Оберіть сторінку для аудиту.', 'seojusai') . '</p></div>';
}

$pages = get_posts([
	'post_type' => ['page'],
	'post_status' => ['publish', 'draft'],
	'numberposts' => 50,
	'orderby' => 'modified',
	'order' => 'DESC',
]);

?>
<div class="wrap seojusai-admin">
	<h1><?php echo esc_html__('Стратегія', 'seojusai'); ?></h1>

	<div class="seojusai-card">
		<h2><?php echo esc_html__('Побудова плану (асинхронно)', 'seojusai'); ?></h2>
		<p class="description"><?php echo esc_html__('План будується на основі результатів аудиту. Натисніть кнопку, щоб поставити аудит у чергу. Синхронний аналіз у цьому екрані не виконується.', 'seojusai'); ?></p>

		<form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
			<?php wp_nonce_field('seojusai_enqueue_page_audit'); ?>
			<input type="hidden" name="action" value="seojusai_enqueue_page_audit" />
			<select name="post_id" class="regular-text">
				<option value="0"><?php echo esc_html__('Оберіть сторінку…', 'seojusai'); ?></option>
				<?php foreach ($pages as $p) : ?>
					<option value="<?php echo (int) $p->ID; ?>"><?php echo esc_html($p->post_title ?: ('#' . (int) $p->ID)); ?></option>
				<?php endforeach; ?>
			</select>
			<button type="submit" class="button button-primary"><?php echo esc_html__('Запланувати аудит та план', 'seojusai'); ?></button>
		</form>
	</div>

	<div class="seojusai-card">
		<h2><?php echo esc_html__('Пояснення', 'seojusai'); ?></h2>
		<ul class="ul-disc">
			<li><?php echo esc_html__('Оцінка та проблеми зберігаються у мета-полях сторінки та відображаються у списку сторінок і в редакторі.', 'seojusai'); ?></li>
			<li><?php echo esc_html__('План формується після події завершення аудиту. Якщо «реальність» (SERP/GSC/Gemini) недоступна — політики безпеки можуть обмежити застосування.', 'seojusai'); ?></li>
		</ul>
	</div>
</div>
