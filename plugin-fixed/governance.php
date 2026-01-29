<?php
declare(strict_types=1);

defined('ABSPATH') || exit;

use SEOJusAI\Governance\RealityBoundary;
use SEOJusAI\Safety\SafeMode;

if (!current_user_can('manage_options')) {
	return;
}

$safe_mode = SafeMode::is_enabled();

$has_reality_boundary = class_exists(RealityBoundary::class);
$has_rest_kernel      = class_exists('SEOJusAI\\Rest\\RestKernel');
$has_explain          = class_exists('SEOJusAI\\Explain\\ExplainService') || class_exists('SEOJusAI\\Explain\\ExplainRepository') || class_exists('SEOJusAI\\Explain\\ExplanationRepository');
$has_conflicts        = file_exists(__DIR__ . '/conflicts.php');

$boundary_status = $has_reality_boundary ? RealityBoundary::status() : [
	'has_serp'   => false,
	'has_gemini' => false,
	'has_gsc'    => false,
	'status'     => 'missing',
	'message'    => __('Межа реальності: відсутній', 'seojusai'),
];

?>
<div class="wrap seojusai-admin">
	<h1><?php echo esc_html__('Післярелізна стабільність', 'seojusai'); ?></h1>

	<div class="seojusai-card">
		<h2><?php echo esc_html__('Статус захисту', 'seojusai'); ?></h2>

		<p>
			<strong><?php echo esc_html__('Безпечний режим:', 'seojusai'); ?></strong>
			<?php echo $safe_mode ? esc_html__('увімкнено', 'seojusai') : esc_html__('вимкнено', 'seojusai'); ?>
		</p>
<form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="margin:12px 0 0;">
	<input type="hidden" name="action" value="<?php echo esc_attr(\SEOJusAI\Safety\SafeModeController::ACTION_TOGGLE); ?>">
	<?php wp_nonce_field(\SEOJusAI\Safety\SafeModeController::ACTION_TOGGLE); ?>
	<?php if ($safe_mode): ?>
		<input type="hidden" name="enable" value="0">
		<input type="hidden" name="reason" value="">
		<button type="submit" class="button"><?php echo esc_html__('Вимкнути Safe Mode', 'seojusai'); ?></button>
	<?php else: ?>
		<input type="hidden" name="enable" value="1">
		<input type="hidden" name="reason" value="manual">
		<button type="submit" class="button button-primary"><?php echo esc_html__('Увімкнути Safe Mode', 'seojusai'); ?></button>
	<?php endif; ?>
	<span class="description" style="margin-left:8px;"><?php echo esc_html__('Safe Mode блокує застосування змін і залишає лише перегляд.', 'seojusai'); ?></span>
</form>

		<p>
			<strong><?php echo esc_html__('Explain:', 'seojusai'); ?></strong>
			<?php echo $has_explain ? esc_html__('доступно', 'seojusai') : esc_html__('відсутньо', 'seojusai'); ?>
		</p>

		<p>
			<strong><?php echo esc_html__('Центр конфліктів:', 'seojusai'); ?></strong>
			<?php echo $has_conflicts ? esc_html__('доступно', 'seojusai') : esc_html__('відсутньо', 'seojusai'); ?>
		</p>

		<p>
			<strong><?php echo esc_html__('Межа реальності:', 'seojusai'); ?></strong>
			<?php echo esc_html((string) ($boundary_status['message'] ?? __('Невідомо', 'seojusai'))); ?>
		</p>

		<p class="description">
			<?php echo esc_html__('Безпека важливіша за швидкість: система блокує будь-які дії без джерела «реальності» (SERP/Gemini/Google).', 'seojusai'); ?>
		</p>
	</div>

	<div class="seojusai-card">
		<h2><?php echo esc_html__('Діагностика', 'seojusai'); ?></h2>

		<ul class="ul-disc">
			<li><?php echo $has_rest_kernel ? esc_html__('REST ядро: активне', 'seojusai') : esc_html__('REST ядро: відсутнє', 'seojusai'); ?></li>
			<li><?php echo !empty($boundary_status['has_serp']) ? esc_html__('SERP: доступно', 'seojusai') : esc_html__('SERP: відсутньо', 'seojusai'); ?></li>
			<li><?php echo !empty($boundary_status['has_gemini']) ? esc_html__('Gemini: доступно', 'seojusai') : esc_html__('Gemini: відсутньо', 'seojusai'); ?></li>
			<li><?php echo !empty($boundary_status['has_gsc']) ? esc_html__('Google Search Console: доступно', 'seojusai') : esc_html__('Google Search Console: відсутньо', 'seojusai'); ?></li>
		</ul>
	</div>
</div>
