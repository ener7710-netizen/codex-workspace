<?php
declare(strict_types=1);

namespace SEOJusAI\Admin\Confirmations;

defined('ABSPATH') || exit;

final class TaskListTable {

	public static function render(array $tasks): void {

		echo '<table class="widefat fixed striped">';
		echo '<thead><tr>';
		echo '<th style="width:40px"></th>';
		echo '<th>Завдання</th>';
		echo '<th>Тип</th>';
		echo '<th>Пріоритет</th>';
		echo '<th>Preview</th>'; // ✅ НОВЕ
		echo '</tr></thead><tbody>';

		foreach ($tasks as $i => $task) {

			$action   = esc_html((string) ($task['action'] ?? ''));
			$type     = esc_html((string) ($task['type'] ?? ''));
			$priority = esc_html((string) ($task['priority'] ?? 'medium'));

			echo '<tr>';

			// checkbox — НЕ ЧІПАЄМО
			echo '<td><input type="checkbox" name="seojusai_tasks[]" value="' . esc_attr((string) $i) . '"></td>';

			echo '<td>' . $action . '</td>';
			echo '<td><code>' . $type . '</code></td>';
			echo '<td>' . strtoupper($priority) . '</td>';

			// ✅ PREVIEW (ШАГ 7)
			echo '<td style="max-width:420px">';
			if (class_exists(TaskPreviewRenderer::class)) {
				TaskPreviewRenderer::render((array) $task);
			} else {
				echo '<em>Preview недоступний</em>';
			}
			echo '</td>';

			echo '</tr>';
		}

		echo '</tbody></table>';
	}
}
