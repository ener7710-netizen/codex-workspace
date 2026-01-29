<?php
defined('ABSPATH') || exit;

use SEOJusAI\Experiments\ExperimentsRepository;

$repo = new ExperimentsRepository();
$experiments = $repo->all();
?>
<div class="wrap">
	<h1><?php echo esc_html__('Експерименти (A/B) — Безпечно', 'seojusai'); ?></h1>
	<p><?php echo esc_html__('Експерименти не змінюють контент сторінок. Зміни застосовуються лише на UI-шарі (JS) та мають sticky-варіант через cookie.', 'seojusai'); ?></p>

	<h2><?php echo esc_html__('Активні та збережені експерименти', 'seojusai'); ?></h2>

	<table class="widefat striped">
		<thead>
			<tr>
				<th>ID</th>
				<th><?php echo esc_html__('Назва', 'seojusai'); ?></th>
				<th><?php echo esc_html__('Статус', 'seojusai'); ?></th>
				<th><?php echo esc_html__('Тип', 'seojusai'); ?></th>
				<th><?php echo esc_html__('Selector', 'seojusai'); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php foreach ($experiments as $e): ?>
				<tr>
					<td><?php echo (int)($e['id'] ?? 0); ?></td>
					<td><?php echo esc_html((string)($e['name'] ?? '')); ?></td>
					<td><?php echo esc_html((string)($e['status'] ?? '')); ?></td>
					<td><?php echo esc_html((string)($e['type'] ?? '')); ?></td>
					<td><code><?php echo esc_html((string)($e['selector'] ?? '')); ?></code></td>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>

	<p><?php echo esc_html__('Керування (створення/пауза/оновлення) робиться через REST API /experiments (для інтеграції з UI).', 'seojusai'); ?></p>
</div>
