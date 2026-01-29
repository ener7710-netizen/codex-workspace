<?php
declare(strict_types=1);

use SEOJusAI\Snapshots\SnapshotRepository;

defined('ABSPATH') || exit;

if (!current_user_can('manage_options')) {
	wp_die(esc_html__('Недостатньо прав доступу.', 'seojusai'), 403);
}

$notice = isset($_GET['seojusai_notice']) ? sanitize_key((string) wp_unslash($_GET['seojusai_notice'])) : '';
if ($notice === 'serp_enqueued') {
	echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('SERP-знімок заплановано в черзі. Результат зʼявиться після виконання завдання.', 'seojusai') . '</p></div>';
} elseif ($notice === 'serp_failed') {
	echo '<div class="notice notice-error is-dismissible"><p>' . esc_html__('Не вдалося запланувати SERP-знімок. Перевірте налаштування та повторіть спробу.', 'seojusai') . '</p></div>';
}

$repo = class_exists(SnapshotRepository::class) ? new SnapshotRepository() : null;
$latest = $repo ? $repo->get_latest('serp', 1, 5) : [];
$default_query = (string) parse_url((string) home_url(), PHP_URL_HOST);

?>
<div class="wrap seojusai-admin">
	<h1><?php echo esc_html__('SERP (Google) — знімки та сигнали', 'seojusai'); ?></h1>

	<div class="seojusai-card">
		<h2><?php echo esc_html__('Згенерувати SERP-знімок', 'seojusai'); ?></h2>
		<p class="description"><?php echo esc_html__('Ця дія планує асинхронне завдання збору SERP. Аналіз не виконується синхронно — результати зʼявляться після завершення черги.', 'seojusai'); ?></p>

		<form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
			<?php wp_nonce_field('seojusai_serp_snapshot'); ?>
			<input type="hidden" name="action" value="seojusai_serp_snapshot" />

			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="serp_query"><?php echo esc_html__('Запит', 'seojusai'); ?></label></th>
					<td>
						<input name="serp_query" id="serp_query" type="text" class="regular-text" value="<?php echo esc_attr($default_query); ?>" />
						<p class="description"><?php echo esc_html__('Ключова фраза або домен (за замовчуванням — ваш домен).', 'seojusai'); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="serp_country"><?php echo esc_html__('Країна', 'seojusai'); ?></label></th>
					<td>
						<select name="serp_country" id="serp_country">
							<option value="ua" selected>UA</option>
							<option value="pl">PL</option>
							<option value="de">DE</option>
							<option value="us">US</option>
						</select>
						<span class="description"><?php echo esc_html__('Використовується для джерела SERP.', 'seojusai'); ?></span>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="serp_lang"><?php echo esc_html__('Мова', 'seojusai'); ?></label></th>
					<td>
						<select name="serp_lang" id="serp_lang">
							<option value="uk" selected>UK</option>
							<option value="ru">RU</option>
							<option value="en">EN</option>
						</select>
					</td>
				</tr>
			</table>

			<p>
				<button type="submit" class="button button-primary"><?php echo esc_html__('Запланувати SERP-знімок', 'seojusai'); ?></button>
			</p>
		</form>
	</div>

	<div class="seojusai-card">
		<h2><?php echo esc_html__('Останні SERP-знімки', 'seojusai'); ?></h2>

		<?php if (empty($latest)) : ?>
			<p class="description"><?php echo esc_html__('Поки що немає збережених SERP-знімків. Заплануйте перший знімок і дочекайтеся виконання черги.', 'seojusai'); ?></p>
		<?php else : ?>
			<table class="widefat striped">
				<thead>
					<tr>
						<th><?php echo esc_html__('Час', 'seojusai'); ?></th>
						<th><?php echo esc_html__('Короткий підсумок', 'seojusai'); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ($latest as $row) : ?>
						<?php
							$data = isset($row['data']) && is_array($row['data']) ? $row['data'] : [];
							$at = isset($row['at']) ? (string) $row['at'] : '';
							$top = isset($data['top']) && is_array($data['top']) ? $data['top'] : [];
							$summary = sprintf(
								/* translators: %d: results count */
								__('Збережено результатів: %d', 'seojusai'),
								count($top)
							);
						?>
						<tr>
							<td><?php echo esc_html($at); ?></td>
							<td><?php echo esc_html($summary); ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		<?php endif; ?>
	</div>
</div>
