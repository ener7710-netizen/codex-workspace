<?php
declare(strict_types=1);

namespace SEOJusAI\Executors;

use SEOJusAI\Core\EmergencyStop;
use SEOJusAI\AI\DecisionContract;

defined('ABSPATH') || exit;

/**
 * ApplyService
 * * ОСТАННЯ ЛІНІЯ ЗАХИСТУ.
 * Виконує фізичні зміни в БД WordPress на основі рішень AI.
 */
final class ApplyService {

	/**
	 * Застосувати пакет рішень.
	 *
	 * @param array $decision Структура згідно з DecisionContract.
	 * @param array $context  ['post_id' => int, 'snapshot_id' => int]
	 */
	public function apply(array $decision, array $context): bool {
		if ( EmergencyStop::is_active() ) {
			return false;
		}

		$post_id     = (int) ($context['post_id'] ?? 0);
		$snapshot_id = (int) ($context['snapshot_id'] ?? 0);

		// Заборонено вносити зміни без можливості відкату (snapshot_id)
		if ( $post_id <= 0 || $snapshot_id <= 0 ) {
			return false;
		}

		// Валідація контракту перед виконанням
		if ( ! DecisionContract::validate($decision) ) {
			do_action('seojusai/apply/blocked_invalid_decision', $post_id);
			return false;
		}

		$actions = $decision['actions'] ?? [];
		if ( empty($actions) ) {
			return true;
		}

		do_action('seojusai/apply/before', $context);

		foreach ( $actions as $action ) {
			$type = sanitize_key((string) ($action['action'] ?? ''));

			try {
				match ($type) {
					'add_faq_schema'     => $this->apply_faq_schema($post_id, $action),
					'add_contact_schema' => $this->apply_contact_schema($post_id),
					'add_internal_link'  => $this->apply_internal_link($post_id, $action),
					'add_section'        => $this->apply_content_section($post_id, $action), // Нове
					default              => null,
				};
			} catch ( \Throwable $e ) {
				do_action('seojusai/apply/error', ['type' => $type, 'error' => $e->getMessage(), 'decision_record_id' => (int)($context['decision_record_id'] ?? 0), 'snapshot_id' => $snapshot_id]);
				return false;
			}
		}

		do_action('seojusai/apply/after', $context);

		return true;
	}

	/* ================= ACTIONS ================= */

	private function apply_faq_schema(int $post_id, array $action): void {
		$faq = $action['faq'] ?? [];
		if ( is_array($faq) && !empty($faq) ) {
			update_post_meta($post_id, '_seojusai_faq_schema', wp_json_encode($faq, JSON_UNESCAPED_UNICODE));
		}
	}

	private function apply_contact_schema(int $post_id): void {
		$schema = [
			'@context'   => 'https://schema.org',
			'@type'      => 'LegalService',
			'name'       => get_bloginfo('name'),
			'url'        => home_url('/'),
			'areaServed' => 'UA',
		];
		update_post_meta($post_id, '_seojusai_contact_schema', wp_json_encode($schema, JSON_UNESCAPED_UNICODE));
	}

	private function apply_internal_link(int $post_id, array $action): void {
		$from = sanitize_text_field((string) ($action['from'] ?? ''));
		$to   = esc_url_raw((string) ($action['to'] ?? ''));

		if ( $from === '' || $to === '' ) return;

		$content = get_post_field('post_content', $post_id);
		if ( str_contains($content, $to) ) return;

		$link = sprintf('<a href="%s">%s</a>', $to, $from);
		// Додаємо в кінець першого абзацу
		$updated = preg_replace('/<\/p>/', " {$link}</p>", $content, 1);

		if ( $updated && $updated !== $content ) {
			wp_update_post(['ID' => $post_id, 'post_content' => $updated]);
		}
	}

	/**
	 * Додавання структури (H2-H4) для адвокатської сторінки.
	 */
	private function apply_content_section(int $post_id, array $action): void {
		$level = sanitize_key($action['level'] ?? 'h2');
		$title = sanitize_text_field($action['title'] ?? '');

		if ( $title === '' ) return;

		$content = get_post_field('post_content', $post_id);
		if ( str_contains($content, $title) ) return;

		$html = "\n<{$level}>" . esc_html($title) . "</{$level}>\n<p></p>\n";

		wp_update_post([
			'ID'           => $post_id,
			'post_content' => $content . $html,
		]);
	}
}
