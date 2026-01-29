<?php
declare(strict_types=1);

defined('ABSPATH') || exit;

if (!current_user_can('manage_options')) {
	wp_die(esc_html__('Недостатньо прав доступу.', 'seojusai'), 403);
}

$notice = isset($_GET['seojusai_notice']) ? sanitize_key((string) wp_unslash($_GET['seojusai_notice'])) : '';
if ($notice === 'audit_enqueued') {
	echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Аудит заплановано. Автопілот працює на базі черги та політик безпеки.', 'seojusai') . '</p></div>';
} elseif ($notice === 'audit_failed') {
	echo '<div class="notice notice-error is-dismissible"><p>' . esc_html__('Не вдалося запланувати аудит.', 'seojusai') . '</p></div>';
}

if ($notice === 'self_trained') {
	echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Self-training completed.', 'seojusai') . '</p></div>';
}

if ($notice === 'draft_enqueued') {
	echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Створення чернетки заплановано. Система побудує структуру на базі конкурентів і додасть SEO-елементи у форматі блоків.', 'seojusai') . '</p></div>';
} elseif ($notice === 'draft_failed') {
	echo '<div class="notice notice-error is-dismissible"><p>' . esc_html__('Не вдалося запланувати створення чернетки.', 'seojusai') . '</p></div>';
} elseif ($notice === 'draft_missing_post') {
	echo '<div class="notice notice-warning is-dismissible"><p>' . esc_html__('Оберіть сторінку для створення чернетки.', 'seojusai') . '</p></div>';
}

$pages = get_posts([
	'post_type' => ['page'],
	'post_status' => ['publish', 'draft'],
	'numberposts' => 50,
	'orderby' => 'modified',
	'order' => 'DESC',
]);

?>
<div class="wrap seojusai-admin">
	<h1><?php echo esc_html__('Автопілот', 'seojusai'); ?></h1>

	<div class="seojusai-card">
		<h2><?php echo esc_html__('Безпечний запуск (черга)', 'seojusai'); ?></h2>
		<p class="description"><?php echo esc_html__('Автопілот не виконує синхронних змін. Спочатку поставте аудит у чергу, щоб сформувати «план» і пояснення. Застосування регулюється режимом та Safe Mode.', 'seojusai'); ?></p>

		<form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
			<?php wp_nonce_field('seojusai_enqueue_page_audit'); ?>
			<input type="hidden" name="action" value="seojusai_enqueue_page_audit" />
			<select name="post_id" class="regular-text">
				<option value="0"><?php echo esc_html__('Оберіть сторінку…', 'seojusai'); ?></option>
				<?php foreach ($pages as $p) : ?>
					<option value="<?php echo (int) $p->ID; ?>"><?php echo esc_html($p->post_title ?: ('#' . (int) $p->ID)); ?></option>
				<?php endforeach; ?>
			</select>
			<button type="submit" class="button button-primary"><?php echo esc_html__('Запланувати аудит (Safe)', 'seojusai'); ?></button>
		</form>
	</div>

	<div class="seojusai-card">
	<h2><?php echo esc_html__('Створити чернетку на базі конкурентів', 'seojusai'); ?></h2>
	<p class="description"><?php echo esc_html__('Створює нову чернетку сторінки за структурою конкурентів (SERP → fingerprints) і додає базові SEO-елементи. Виконується тільки через чергу, без синхронного аналізу.', 'seojusai'); ?></p>

	<form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
		<?php wp_nonce_field('seojusai_enqueue_draft_from_competitors'); ?>
		<input type="hidden" name="action" value="seojusai_enqueue_draft_from_competitors" />
		<select name="post_id" class="regular-text">
			<option value="0"><?php echo esc_html__('Оберіть сторінку…', 'seojusai'); ?></option>
			<?php foreach ($pages as $p) : ?>
				<option value="<?php echo (int) $p->ID; ?>"><?php echo esc_html($p->post_title ?: ('#' . (int) $p->ID)); ?></option>
			<?php endforeach; ?>
		</select>
		<button type="submit" class="button"><?php echo esc_html__('Запланувати створення чернетки', 'seojusai'); ?></button>
	</form>
</div>

	<div class="seojusai-card">
		<h2><?php echo esc_html__('Статус безпеки', 'seojusai'); ?></h2>
		<p><?php echo esc_html__('Цей екран показує лише керування запуском у черзі. Для політик і «реальності» відкрийте розділ «Управління» (Governance).', 'seojusai'); ?></p>
		<p><a class="button" href="<?php echo esc_url(admin_url('admin.php?page=seojusai-governance')); ?>"><?php echo esc_html__('Відкрити управління', 'seojusai'); ?></a></p>
	</div>
</div>

    <div class="seojusai-card">
        <h2><?php esc_html_e('Decision review', 'seojusai'); ?></h2>
        <?php
        $pending = $wpdb->get_results(
            "SELECT * FROM {$wpdb->prefix}seojusai_decisions WHERE status = 'planned' ORDER BY created_at DESC LIMIT 20"
        );
        if ($pending):
        ?>
        <table class="widefat striped">
            <thead>
                <tr>
                    <th><?php esc_html_e('Post','seojusai'); ?></th>
                    <th><?php esc_html_e('Score','seojusai'); ?></th>
                    <th><?php esc_html_e('Predictions (incl. self-trained)','seojusai'); ?></th>
                    <th><?php esc_html_e('Action','seojusai'); ?></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($pending as $d): ?>
                <tr>
                    <td>
                        <a href="<?php echo esc_url(get_edit_post_link((int)$d->post_id)); ?>">
                            #<?php echo (int)$d->post_id; ?>
                        </a>
                    </td>
                    <td><?php echo esc_html($d->score); ?></td>
                    <td><?php
    echo esc_html($d->summary);
    echo '<hr/>';
    $items = \SEOJusAI\Repository\DecisionItemRepository::get_latest_by_decision($d->decision_hash);
    foreach ($items as $it) {
        echo '<strong>' . esc_html($it['taxonomy']) . '</strong>: ';
        echo esc_html($it['label']) . ' (' . esc_html(round((float)$it['confidence'],3)) . ')';
        if (!empty($it['rationale'])) {
            echo '<br/><em>' . esc_html($it['rationale']) . '</em>';
        }
        echo '<br/><br/>';
    }
?></td>
                    <td>
                        <a class="button button-primary"
                           href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=seojusai_decision_approve&decision_hash=' . urlencode($d->decision_hash) ), 'seojusai_decision_action', '_wpnonce' ) ); ?>">
                           <?php esc_html_e('Approve','seojusai'); ?></a>
                        <a class="button button-secondary" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=seojusai_seo_meta_apply&decision_hash=' . urlencode($d->decision_hash) ), 'seojusai_decision_action', '_wpnonce' ) ); ?>"><?php esc_html_e('Apply','seojusai'); ?>
                        </a>
                        <a class="button"
                           href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=seojusai_decision_reject&decision_hash=' . urlencode($d->decision_hash) ), 'seojusai_decision_action', '_wpnonce' ) ); ?>">
                           <?php esc_html_e('Reject','seojusai'); ?>
                        </a>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php else: ?>
            <p><?php esc_html_e('No decisions pending review.', 'seojusai'); ?></p>
        <?php endif; ?>
    </div>
    

    <div class="seojusai-card">
        <h2><?php esc_html_e('Decision preview', 'seojusai'); ?></h2>
        <?php
        $decisions=$wpdb->get_results("SELECT * FROM {$wpdb->prefix}seojusai_decisions ORDER BY id DESC LIMIT 10");
        if($decisions):
        ?>
        <table class="widefat">
            <thead><tr><th>Post</th><th>Score</th><th>Status</th><th>Summary</th></tr></thead>
            <tbody>
            <?php foreach($decisions as $d): ?>
                <tr>
                    <td><?php echo (int)$d->post_id; ?></td>
                    <td><?php echo esc_html($d->score); ?></td>
                    <td><?php echo esc_html($d->status); ?></td>
                    <td><?php
    echo esc_html($d->summary);
    echo '<hr/>';
    $items = \SEOJusAI\Repository\DecisionItemRepository::get_latest_by_decision($d->decision_hash);
    foreach ($items as $it) {
        echo '<strong>' . esc_html($it['taxonomy']) . '</strong>: ';
        echo esc_html($it['label']) . ' (' . esc_html(round((float)$it['confidence'],3)) . ')';
        if (!empty($it['rationale'])) {
            echo '<br/><em>' . esc_html($it['rationale']) . '</em>';
        }
        echo '<br/><br/>';
    }
?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php else: ?>
            <p><?php esc_html_e('Decisions not created yet.', 'seojusai'); ?></p>
        <?php endif; ?>
    </div>
    
    <div class="seojusai-card">
        <h2><?php esc_html_e('Explainability / Audit trail', 'seojusai'); ?></h2>
        <p class="description"><?php esc_html_e('Останні події Autopilot з поясненнями.', 'seojusai'); ?></p>
        <?php
        global $wpdb;
        $rows = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}seojusai_audit ORDER BY id DESC LIMIT 20");
        if ($rows):
        ?>
        <table class="widefat">
            <thead><tr><th><?php esc_html_e('Час','seojusai'); ?></th><th><?php esc_html_e('Подія','seojusai'); ?></th><th><?php esc_html_e('Повідомлення','seojusai'); ?></th></tr></thead>
            <tbody>
            <?php foreach ($rows as $r): ?>
                <tr>
                    <td><?php echo esc_html($r->created_at); ?></td>
                    <td><?php echo esc_html($r->event); ?></td>
                    <td><?php echo esc_html($r->message); ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php else: ?>
            <p><?php esc_html_e('Подій поки немає.', 'seojusai'); ?></p>
        <?php endif; ?>
    </div>
    

    <hr/>
    <div class="seojusai-card">
        <h2><?php echo esc_html__('Налаштування Autopilot', 'seojusai'); ?></h2>
        <form method="post" action="<?php echo esc_url(admin_url('options.php')); ?>">
            <?php settings_fields('seojusai_autopilot'); ?>
            <table class="form-table">
                <tr>
                    <th><?php esc_html_e('Увімкнути Autopilot', 'seojusai'); ?></th>
                    <td><input type="checkbox" name="seojusai_execution_enabled" value="1" <?php checked(get_option('seojusai_execution_enabled')); ?> /></td>
                </tr>
                <tr>
                    <th><?php esc_html_e('API Key (X-SEOJusAI-Key)', 'seojusai'); ?></th>
                    <td><input type="text" name="seojusai_api_key" value="<?php echo esc_attr(get_option('seojusai_api_key','')); ?>" style="width:320px;" />
                    <p class="description"><?php esc_html_e('If set, REST API requires header X-SEOJusAI-Key.', 'seojusai'); ?></p></td>
                </tr>
                <tr>
                    <th><?php esc_html_e('Макс. авто-задач за хвилину', 'seojusai'); ?></th>
                    <td><input type="number" min="1" max="20" name="seojusai_autopilot_max_auto_per_minute" value="<?php echo esc_attr(get_option('seojusai_autopilot_max_auto_per_minute',3)); ?>" /></td>
                </tr>
                <tr>
                    <th><?php esc_html_e('Макс. авто-задач на пост/год', 'seojusai'); ?></th>
                    <td><input type="number" min="1" max="20" name="seojusai_autopilot_max_auto_per_post_hour" value="<?php echo esc_attr(get_option('seojusai_autopilot_max_auto_per_post_hour',2)); ?>" /></td>
                </tr>
                <tr>
                    <th><?php esc_html_e('Self-training (з approved рішень)', 'seojusai'); ?></th>
                    <td><label><input type="checkbox" name="seojusai_self_training_enabled" value="1" <?php checked(get_option('seojusai_self_training_enabled')); ?> /> <?php esc_html_e('Enable', 'seojusai'); ?></label></td>
                </tr>
                <tr>
                    <th><?php esc_html_e('Max samples for self-training', 'seojusai'); ?></th>
                    <td><input type="number" min="50" max="2000" name="seojusai_self_training_max_samples" value="<?php echo esc_attr(get_option('seojusai_self_training_max_samples',500)); ?>" /></td>
                </tr>
                <tr>
                    <th><?php esc_html_e('Поріг fail-burst (10 хв)', 'seojusai'); ?></th>
                    <td><input type="number" min="3" max="20" name="seojusai_autopilot_fail_burst_threshold" value="<?php echo esc_attr(get_option('seojusai_autopilot_fail_burst_threshold',5)); ?>" /></td>
                </tr>
            </table>
            <?php submit_button(); ?>
            <p>
                <a class="button" href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=seojusai_self_train_now'), 'seojusai_self_train_now')); ?>"><?php esc_html_e('Run self-training now', 'seojusai'); ?></a>
            </p>
        </form>
    </div>
    