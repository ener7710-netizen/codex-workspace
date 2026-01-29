<?php
declare(strict_types=1);

use SEOJusAI\Capabilities\CapabilityGuard;
use SEOJusAI\Capabilities\CapabilityMap;
use SEOJusAI\Learning\LearningEventRepository;
use SEOJusAI\Learning\LearningService;
use SEOJusAI\Input\Input;
use SEOJusAI\Tasks\TaskQueue;

defined('ABSPATH') || exit;

if (!CapabilityGuard::can(CapabilityMap::MANAGE_SETTINGS)) {
    wp_die(__('Недостатньо прав доступу.', 'seojusai'));
}

$repo = new LearningEventRepository();

if ((Input::post('seojusai_learning_action', null) !== null)) {
	check_admin_referer('seojusai_learning_action');
	$act = sanitize_key((string) Input::post('seojusai_learning_action'));

	if ($act === 'save') {
		$enabled = (Input::post('learning_enabled', null) !== null) ? (bool) Input::post('learning_enabled') : false;
		update_option('seojusai_learning_enabled', $enabled, false);

		$days = (Input::post('observe_days', null) !== null) ? (int) Input::post('observe_days') : 7;
		$days = max(1, min(60, $days));
		update_option('seojusai_learning_observe_days', $days, false);

		echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Налаштування збережено.', 'seojusai') . '</p></div>';
	}

	if ($act === 'run_now') {
		$task_id = 0;
		try {
			$queue = new TaskQueue();
			$task_id = (int) $queue->enqueue('learning/run_weekly', [
				'priority' => 'low',
				'max_attempts' => 1,
				'source' => 'admin:learning',
			], 'learning:run_weekly');
		} catch (\Throwable $e) {
			$task_id = 0;
		}

		if ($task_id > 0) {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Завдання самонавчання додано до черги.', 'seojusai') . '</p></div>';
		} else {
			echo '<div class="notice notice-error is-dismissible"><p>' . esc_html__('Не вдалося додати завдання до черги.', 'seojusai') . '</p></div>';
		}
	}
}

$enabled = (bool) get_option('seojusai_learning_enabled', true);
$days = (int) get_option('seojusai_learning_observe_days', 7);
 = (bool)get_option('seojusai_learning_enabled', true);
$days = (int)get_option('seojusai_learning_observe_days', 7);

$status = (Input::get('status', null) !== null) ? sanitize_key((string)Input::get('status')) : '';
$rows = $repo->list_recent(60, $status);

$base_url = menu_page_url('seojusai-learning', false);

?>
<div class="wrap">
    <h1><?php echo esc_html__('Самонавчання', 'seojusai'); ?></h1>

    <div class="card" style="max-width:100%; border-left:4px solid #00a32a;">
        <h2 style="margin-top:0;"><?php echo esc_html__('Налаштування', 'seojusai'); ?></h2>
        <form method="post" style="display:flex; gap:12px; flex-wrap:wrap; align-items:flex-end;">
            <?php wp_nonce_field('seojusai_learning_action'); ?>
            <input type="hidden" name="seojusai_learning_action" value="save" />
            <div>
                <label><strong><?php echo esc_html__('Увімкнено', 'seojusai'); ?></strong></label><br>
                <label><input type="checkbox" name="learning_enabled" value="1" <?= $enabled ? 'checked' : '' ?> /> <?php echo esc_html__('Так', 'seojusai'); ?></label>
            </div>
            <div>
                <label><strong><?php echo esc_html__('Вікно спостереження (днів)', 'seojusai'); ?></strong></label><br>
                <input type="number" name="observe_days" min="1" max="60" value="<?= (int)$days ?>" />
            </div>
            <div><button class="button button-primary" type="submit"><?php echo esc_html__('Зберегти', 'seojusai'); ?></button></div>
        </form>
        <form method="post" style="margin-top:10px;">
            <?php wp_nonce_field('seojusai_learning_action'); ?>
            <input type="hidden" name="seojusai_learning_action" value="run_now" />
            <button class="button button-secondary" type="submit"><?php echo esc_html__('Запустити самонавчання зараз', 'seojusai'); ?></button>
        </form>
        <p style="margin-top:10px;">
            Після застосування рішень Autopilot/Approve система збере “before/after” метрики і збереже outcome у базу.
        </p>
    </div>

    <div class="card" style="max-width:100%; margin-top:14px;">

        <h2 style="margin-top:0;">Події</h2>

        <p>
            Фільтр:
            <a href="<?= esc_url($base_url) ?>">Усі</a> |
            <a href="<?= esc_url(add_query_arg('status','scheduled',$base_url)) ?>">scheduled</a> |
            <a href="<?= esc_url(add_query_arg('status','observed',$base_url)) ?>">observed</a> |
            <a href="<?= esc_url(add_query_arg('status','retry',$base_url)) ?>">retry</a>
        </p>

        <table class="widefat striped">
            <thead>
                <tr>
                    <th style="width:70px;">ID</th>
                    <th style="width:90px;">Risk</th>
                    <th style="width:80px;">Conf</th>
                    <th>decision_hash</th>
                    <th style="width:140px;">Entity</th>
                    <th style="width:140px;">Статус</th>
                    <th style="width:190px;">observe_after</th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($rows)): ?>
                <tr><td colspan="7">Немає подій.</td></tr>
            <?php else: ?>
                <?php foreach ($rows as $r): ?>
                    <tr>
                        <td><?= (int)$r['id'] ?></td>
                        <td><code><?= esc_html((string)$r['predicted_risk']) ?></code></td>
                        <td><?= esc_html(number_format((float)($r['confidence'] ?? 0), 2)) ?></td>
                        <td><code><?= esc_html((string)$r['decision_hash']) ?></code></td>
                        <td><?= esc_html((string)$r['entity_type']) ?>:<?= (int)$r['entity_id'] ?></td>
                        <td><code><?= esc_html((string)$r['status']) ?></code></td>
                        <td><small><?= esc_html((string)$r['observe_after']) ?></small></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>

        <p style="margin-top:10px;"><em>Примітка:</em> детальну візуалізацію outcome (графіки/дельти) зробимо наступним кроком.</p>
    <div class="card" style="max-width:100%; margin-top:14px; border-left:4px solid #2271b1;">
    <h2 style="margin-top:0;">Калібрування (v1)</h2>
    <?php
        $stats = get_option('seojusai_calibration_stats', []);
        if (!is_array($stats)) $stats = [];
    ?>
    <?php if (empty($stats)): ?>
        <p><em>Ще немає observed-подій для калібрування.</em></p>
    <?php else: ?>
        <table class="widefat striped">
            <thead>
                <tr>
                    <th>Дія key</th>
                    <th style="width:90px;">Observed</th>
                    <th style="width:90px;">Success%</th>
                    <th style="width:120px;">Avg clicks Δ</th>
                    <th style="width:120px;">Avg impr Δ</th>
                    <th style="width:120px;">Avg pos Δ</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($stats as $k => $v): ?>
                <?php
                    $obs = (int)($v['observed'] ?? 0);
                    $succ = (int)($v['success'] ?? 0);
                    $rate = $obs ? round(($succ/$obs)*100, 1) : 0;
                ?>
                <tr>
                    <td><code><?= esc_html((string)$k) ?></code></td>
                    <td><?= $obs ?></td>
                    <td><?= esc_html((string)$rate) ?>%</td>
                    <td><?= esc_html(number_format((float)($v['avg_clicks_delta'] ?? 0), 2)) ?></td>
                    <td><?= esc_html(number_format((float)($v['avg_impr_delta'] ?? 0), 2)) ?></td>
                    <td><?= esc_html(number_format((float)($v['avg_pos_delta'] ?? 0), 2)) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <p style="margin-top:10px;"><em>Як впливає:</em> успішні дії підвищують confidence у нових рішеннях, неуспішні — знижують і можуть підняти risk.</p>
    <?php endif; ?>
</div>

</div>
</div>
