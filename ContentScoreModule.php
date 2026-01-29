<?php
declare(strict_types=1);

namespace SEOJusAI\Modules;

use SEOJusAI\Core\Contracts\ModuleInterface;
use SEOJusAI\Core\Kernel;
use SEOJusAI\ContentScore\ScoreCalculator;

defined('ABSPATH') || exit;

final class ContentScoreModule implements ModuleInterface {

	public function get_slug(): string { return 'content_score'; }

	public function register(Kernel $kernel): void {
		$kernel->register_module($this->get_slug(), $this);
	}

	public function init(Kernel $kernel): void {
		add_action('save_post', function (int $post_id, \WP_Post $post) {
			if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
			if (wp_is_post_revision($post_id)) return;
			if (!in_array($post->post_status, ['publish','draft','pending','future','private'], true)) return;
			if (!\SEOJusAI\seojusai_should_process_post($post_id)) return;
			(new ScoreCalculator())->persist($post_id);
		}, 25, 2);

	}
}
