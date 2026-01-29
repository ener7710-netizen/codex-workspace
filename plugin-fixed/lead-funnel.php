<?php
defined('ABSPATH') || exit;

use SEOJusAI\LeadFunnel\LeadFunnelService;

$svc = new LeadFunnelService();
$top = $svc->top_pages_by_impact(20);
?>
<div class="wrap">
	<h1><?php echo esc_html__('Lead Funnel (Юридичні звернення)', 'seojusai'); ?></h1>
	<p><?php echo esc_html__('Рекомендації CTA без автозмін. Показує сторінки з найбільшим потенціалом звернень.', 'seojusai'); ?></p>

	<h2><?php echo esc_html__('Топ сторінок за потенціалом (ROI)', 'seojusai'); ?></h2>
	<table class="widefat striped">
		<thead>
			<tr>
				<th><?php echo esc_html__('Сторінка', 'seojusai'); ?></th>
				<th><?php echo esc_html__('Намір', 'seojusai'); ?></th>
				<th><?php echo esc_html__('Рекомендований CTA', 'seojusai'); ?></th>
				<th><?php echo esc_html__('Impact', 'seojusai'); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php foreach ($top as $row): ?>
				<tr>
					<td>
						<a href="<?php echo esc_url(get_edit_post_link((int)$row['post_id'])); ?>">
							<?php echo esc_html($row['title']); ?>
						</a>
					</td>
					<td><?php echo esc_html($row['intent']); ?></td>
					<td><?php echo esc_html($row['cta']); ?></td>
					<td><strong><?php echo (int)$row['impact']; ?></strong></td>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>
</div>
