<?php
declare(strict_types=1);

namespace SEOJusAI\Experiments;

defined('ABSPATH') || exit;

final class ExperimentsFrontend {

	public static function register(): void {
		add_action('wp_enqueue_scripts', [self::class, 'enqueue']);
	}

	public static function enqueue(): void {
		if (is_admin()) {
			return;
		}
		$repo = new ExperimentsRepository();
		$active = $repo->active();
		if (!$active) {
			return;
		}
		$handle = 'seojusai-experiments';
		$src = plugins_url('assets/js/experiments.js', dirname(__FILE__, 3) . '/seojusai.php');
		wp_enqueue_script($handle, $src, [], SEOJUSAI_VERSION, true);
		wp_localize_script($handle, 'SEOJusAIExperiments', [
			'experiments' => $active,
			'cookiePrefix' => 'seojusai_exp_',
		]);
	}
}
