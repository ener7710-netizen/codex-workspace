<?php
declare(strict_types=1);

namespace SEOJusAI\Admin;

use SEOJusAI\Meta\MetaRepository;
use SEOJusAI\ContentScore\ScoreCalculator;
use SEOJusAI\Proposals\ProposalBuilder;
use SEOJusAI\Schema\Builder\SchemaBuilder;
use SEOJusAI\Input\Input;

defined('ABSPATH') || exit;

use SEOJusAI\Admin\DecisionReviewController;
use SEOJusAI\Admin\SeoMetaReviewController;
use SEOJusAI\Admin\SeoMetaApplyController;

final class Metabox {

	public function register(): void {
        (new DecisionReviewController())->register();
        (new SeoMetaReviewController())->register();
        (new SeoMetaApplyController())->register();
		add_action('add_meta_boxes', [$this, 'add']);
		add_action('save_post', [$this, 'save'], 20, 2);
		// @boundary Execution is controlled exclusively by AutopilotExecutionLoop.
		// ❌ Removed: Execution/analysis must occur only via AutopilotExecutionLoop.
		// add_action('wp_ajax_seojusai_generate_proposals', [$this, 'ajax_generate_proposals']);
	}

	public function add(): void {
		add_meta_box(
			'seojusai_metabox',
			__('SEOJusAI', 'seojusai'),
			[$this, 'render'],
			null,
			'side',
			'high'
		);
	}

	public function render(\WP_Post $post): void {
		$repo = new MetaRepository();
		$meta = $repo->get((int)$post->ID);

		$score = ScoreCalculator::load((int)$post->ID);
		$score_value = isset($score['score']) ? (int) $score['score'] : null;

		$proposals = ProposalBuilder::load((int)$post->ID);

		$schema_type = (string) get_post_meta((int)$post->ID, SchemaBuilder::META_TYPE, true);
		$schema_data_json = (string) get_post_meta((int)$post->ID, SchemaBuilder::META_DATA, true);
		$schema_data = $schema_data_json ? json_decode($schema_data_json, true) : [];
		$schema_data = is_array($schema_data) ? $schema_data : [];

		wp_nonce_field('seojusai_metabox_save', 'seojusai_metabox_nonce');

		?>
		<div id="seojusai-metabox" class="seojusai-metabox">
			<p style="margin:0 0 10px;">
				<strong><?php echo esc_html__('Оцінка контенту:', 'seojusai'); ?></strong>
				<span style="font-size:16px;"><?php echo $score_value !== null ? esc_html((string)$score_value . '/100') : esc_html__('—', 'seojusai'); ?></span>
			</p>

			<p style="margin:0 0 10px;">
				<small>
					<?php echo esc_html__('Ручна генерація пропозицій вимкнена. Виконання контролюється виключно через AutopilotExecutionLoop.', 'seojusai'); ?>
				</small>
			</p>

			<div id="seojusai-proposals-box" style="margin:0 0 10px;">
				<?php if (!empty($proposals)) : ?>
					<ul style="margin:0 0 0 18px; list-style:disc;">
						<?php foreach (array_slice($proposals, 0, 5) as $p) : ?>
							<li><strong><?php echo esc_html((string)($p['title'] ?? '')); ?></strong><br>
								<small><?php echo esc_html((string)($p['details'] ?? '')); ?></small>
							</li>
						<?php endforeach; ?>
					</ul>
				<?php else: ?>
					<small><?php echo esc_html__('Ще немає пропозицій.', 'seojusai'); ?></small>
				<?php endif; ?>
			</div>

			<hr>

			<p style="margin:0 0 6px;"><strong><?php echo esc_html__('Meta та Snippet', 'seojusai'); ?></strong></p>

			<p style="margin:0 0 8px;">
				<label style="display:block;font-weight:600;"><?php echo esc_html__('SEO Title', 'seojusai'); ?></label>
				<input type="text" name="seojusai_meta[title]" class="widefat" value="<?php echo esc_attr($meta['title']); ?>" placeholder="<?php echo esc_attr__('Напр.: Адвокат у Києві — консультація та захист', 'seojusai'); ?>">
			</p>

			<p style="margin:0 0 8px;">
				<label style="display:block;font-weight:600;"><?php echo esc_html__('Meta description', 'seojusai'); ?></label>
				<textarea name="seojusai_meta[description]" class="widefat" rows="3" placeholder="<?php echo esc_attr__('Коротко про вигоду та наступний крок для клієнта…', 'seojusai'); ?>"><?php echo esc_textarea($meta['description']); ?></textarea>
			</p>

			<p style="margin:0 0 8px;">
				<label style="display:block;font-weight:600;"><?php echo esc_html__('Canonical URL', 'seojusai'); ?></label>
				<input type="url" name="seojusai_meta[canonical]" class="widefat" value="<?php echo esc_attr($meta['canonical']); ?>" placeholder="<?php echo esc_attr__('За замовчуванням: поточна сторінка', 'seojusai'); ?>">
			</p>

			<p style="margin:0 0 8px;">
				<label style="display:block;font-weight:600;"><?php echo esc_html__('Robots', 'seojusai'); ?></label>
				<input type="text" name="seojusai_meta[robots]" class="widefat" value="<?php echo esc_attr($meta['robots']); ?>" placeholder="<?php echo esc_attr__('Напр.: index,follow або noindex,nofollow', 'seojusai'); ?>">
				<small><?php echo esc_html__('Залиште порожнім, щоб використовувати типові налаштування.', 'seojusai'); ?></small>
			</p>

			<hr>

			<p style="margin:0 0 6px;"><strong><?php echo esc_html__('Schema Builder', 'seojusai'); ?></strong></p>

			<p style="margin:0 0 8px;">
				<label style="display:block;font-weight:600;"><?php echo esc_html__('Тип Schema', 'seojusai'); ?></label>
				<select class="widefat" name="seojusai_schema[type]">
					<option value=""><?php echo esc_html__('Не задано', 'seojusai'); ?></option>
					<option value="article" <?php selected($schema_type, 'article'); ?>><?php echo esc_html__('Article', 'seojusai'); ?></option>
					<option value="legalservice" <?php selected($schema_type, 'legalservice'); ?>><?php echo esc_html__('LegalService', 'seojusai'); ?></option>
				</select>
			</p>

			<div style="margin:0 0 8px;">
				<label style="display:block;font-weight:600;"><?php echo esc_html__('Дані', 'seojusai'); ?></label>
				<input type="text" class="widefat" name="seojusai_schema[city]" value="<?php echo esc_attr((string)($schema_data['city'] ?? '')); ?>" placeholder="<?php echo esc_attr__('Місто (для LegalService)', 'seojusai'); ?>">
				<input type="text" class="widefat" name="seojusai_schema[phone]" value="<?php echo esc_attr((string)($schema_data['phone'] ?? '')); ?>" placeholder="<?php echo esc_attr__('Телефон (для LegalService)', 'seojusai'); ?>" style="margin-top:6px;">
				<input type="text" class="widefat" name="seojusai_schema[author]" value="<?php echo esc_attr((string)($schema_data['author'] ?? '')); ?>" placeholder="<?php echo esc_attr__('Автор (для Article)', 'seojusai'); ?>" style="margin-top:6px;">
			</div>

			<p><small><?php echo esc_html__('Schema буде згенерована та виведена на фронтенді як JSON-LD.', 'seojusai'); ?></small></p>

		</div>
		<?php
	}

	public function save(int $post_id, \WP_Post $post): void {
		if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
		if (!(Input::post('seojusai_metabox_nonce', null) !== null) || !wp_verify_nonce((string)Input::post('seojusai_metabox_nonce'), 'seojusai_metabox_save')) return;
		if (!current_user_can('edit_post', $post_id)) return;

		// Meta
		$meta = (Input::post('seojusai_meta', null) !== null) ? (array) wp_unslash(Input::post('seojusai_meta')) : [];
		(new MetaRepository())->save($post_id, $meta);

		// Schema
		$schema = (Input::post('seojusai_schema', null) !== null) ? (array) wp_unslash(Input::post('seojusai_schema')) : [];
		$type = isset($schema['type']) ? (string) $schema['type'] : '';
		$data = [
			'city' => isset($schema['city']) ? (string) $schema['city'] : '',
			'phone' => isset($schema['phone']) ? (string) $schema['phone'] : '',
			'author' => isset($schema['author']) ? (string) $schema['author'] : '',
		];
		(new SchemaBuilder())->persist($post_id, $type, $data);

		// Content score & proposals refresh
        // @boundary Execution is controlled exclusively by AutopilotExecutionLoop.
        // ❌ Removed: Execution/analysis must occur only via AutopilotExecutionLoop.
        // (new ScoreCalculator())->persist($post_id);
        // (new ProposalBuilder())->persist($post_id);
	}

	public function ajax_generate_proposals(): void {
		check_ajax_referer('seojusai_generate_proposals');

		// @boundary Execution is controlled exclusively by AutopilotExecutionLoop.
		// ❌ Removed: Execution/analysis must occur only via AutopilotExecutionLoop.
		wp_send_json_error(['message' => __('Ручна генерація вимкнена. Виконання доступне лише через Autopilot.', 'seojusai')], 403);

		$post_id = (Input::post('post_id', null) !== null) ? (int) Input::post('post_id') : 0;
		if ($post_id <= 0 || !current_user_can('edit_post', $post_id)) {
			wp_send_json_error(['message' => __('Недостатньо прав.', 'seojusai')], 403);
		}

		(new ScoreCalculator())->persist($post_id);
		$items = (new ProposalBuilder())->persist($post_id);

		wp_send_json_success(['items' => $items]);
	}
}
