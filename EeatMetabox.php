<?php
declare(strict_types=1);

namespace SEOJusAI\Eeat;

use SEOJusAI\Core\I18n;
use SEOJusAI\Input\Input;

defined('ABSPATH') || exit;

final class EeatMetabox {

	public function register(): void {
		add_action('add_meta_boxes', [$this, 'add']);
		add_action('save_post', [$this, 'save'], 20, 2);
	}

	public function add(): void {
		add_meta_box(
			'seojusai-eeat',
			I18n::t('E-E-A-T'),
			[$this, 'render'],
			null,
			'side',
			'default'
		);
	}

	public function render(\WP_Post $post): void {

		$data = EeatRepository::get($post->ID);

		wp_nonce_field('seojusai_eeat', '_seojusai_eeat_nonce');

		$this->field('author', I18n::t('Автор / Експерт'), $data);
		$this->field('experience', I18n::t('Досвід (роки)'), $data);
		$this->field('credentials', I18n::t('Статус / ліцензія'), $data);
		$this->field('trust', I18n::t('Фактори довіри'), $data);
	}

	private function field(string $key, string $label, array $data): void {
		$value = esc_attr((string) ($data[$key] ?? ''));
		echo '<p><label>' . esc_html($label) . '</label>';
		echo "<input type='text' name='seojusai_eeat[{$key}]' value='{$value}' style='width:100%'></p>";
	}

	public function save(int $post_id, \WP_Post $post): void {

		if (
			(defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) ||
			!(Input::post('_seojusai_eeat_nonce', null) !== null) ||
			!wp_verify_nonce(Input::post('_seojusai_eeat_nonce'), 'seojusai_eeat')
		) {
			return;
		}

		if (!current_user_can('edit_post', $post_id)) {
			return;
		}

		$data = (array) (Input::post('seojusai_eeat') ?? []);
		EeatRepository::save($post_id, array_map('sanitize_text_field', $data));
	}
}
