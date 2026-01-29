<?php
declare(strict_types=1);

namespace SEOJusAI\Admin\Confirmations;

use SEOJusAI\Admin\Tasks\TaskPreview;

defined('ABSPATH') || exit;

final class TaskPreviewRenderer {

	public static function render(array $task): void {

		echo '<div class="seojusai-task-preview">';

		/* ============================
		 * AI RECOMMENDATION (БЫЛО)
		 * ============================ */

		echo '<h2>AI Recommendation</h2>';

		if (!empty($task['action'])) {
			echo '<p><strong>Дія:</strong> ' . esc_html((string) $task['action']) . '</p>';
		}

		if (!empty($task['decision'])) {
			echo '<pre style="
				background:#f8fafc;
				padding:10px;
				border-radius:6px;
				font-size:12px;
				overflow:auto;
				max-height:200px;
			">';
			echo esc_html(
				wp_json_encode(
					$task['decision'],
					JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE
				)
			);
			echo '</pre>';
		}

		/* ============================
		 * PREVIEW / DIFF (ШАГ 7)
		 * ============================ */

		if (class_exists(TaskPreview::class)) {
			TaskPreview::render($task);
		} else {
			echo '<p><em>Preview недоступний</em></p>';
		}

		echo '</div>';
	}
}
