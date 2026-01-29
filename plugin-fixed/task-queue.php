<?php
declare(strict_types=1);

use SEOJusAI\Capabilities\CapabilityGuard;
use SEOJusAI\Capabilities\CapabilityMap;
use SEOJusAI\Tasks\TaskQueue;
use SEOJusAI\Tasks\ActionSchedulerBridge;
use SEOJusAI\Input\Input;

defined('ABSPATH') || exit;

if (!CapabilityGuard::can(CapabilityMap::MANAGE_SETTINGS)) {
    wp_die(__('Недостатньо прав доступу.', 'seojusai'));
}

$queue = new TaskQueue();
$status = (Input::get('status', null) !== null) ? sanitize_key((string)Input::get('status')) : '';
$action = (Input::post('seojusai_queue_action', null) !== null) ? sanitize_key((string)Input::post('seojusai_queue_action')) : '';
$task_id = (Input::post('task_id', null) !== null) ? (int)Input::post('task_id') : 0;

if ($action && $task_id > 0) {
    check_admin_referer('seojusai_queue_action');
    if ($action === 'retry') {
        $queue->retry_now($task_id);
        echo '<div class="notice notice-success is-dismissible"><p>Задачу поставлено в чергу повторно.</p></div>';
    }
    if ($action === 'delete') {
        $queue->delete($task_id);
        echo '<div class="notice notice-success is-dismissible"><p>Задачу видалено.</p></div>';
    }
}

$items = $queue->list(50, 0, $status);

// quick stats
global $wpdb;
$table = $wpdb->prefix . 'seojusai_tasks';
$counts = $wpdb->get_results("SELECT status, COUNT(1) c FROM {$table} GROUP BY status", ARRAY_A);
$stat = [];
foreach (($counts ?: []) as $r) { $stat[(string)$r['status']] = (int)$r['c']; }

$has_as = ActionSchedulerBridge::available();
$settings_action = (Input::post('seojusai_queue_settings', null) !== null) ? sanitize_key((string)Input::post('seojusai_queue_settings')) : '';
if ($settings_action === 'save') {
    check_admin_referer('seojusai_queue_settings');
    $backend = (Input::post('tasks_backend', null) !== null) ? sanitize_key((string)Input::post('tasks_backend')) : '';
    if ($backend !== 'as' && $backend !== 'db') $backend = $has_as ? 'as' : 'db';
    update_option('seojusai_tasks_backend', $backend, false);

    $conc = (Input::post('tasks_concurrency', null) !== null) ? (int)Input::post('tasks_concurrency') : 5;
    if ($conc < 1) $conc = 1;
    if ($conc > 50) $conc = 50;
    update_option('seojusai_tasks_concurrency', $conc, false);

    echo '<div class="notice notice-success is-dismissible"><p>Налаштування черги збережено.</p></div>';
}



$statuses = [
    '' => 'Усі',
    'pending' => 'Очікує',
    'running' => 'Виконується',
    'executed' => 'Виконано',
    'failed' => 'Помилка',
    'dead' => 'Dead-letter',
];

?>
<div class="wrap">
    <h1>Черга задач</h1>
<div class="card" style="max-width: 100%; margin: 10px 0 16px; border-left: 4px solid #2271b1;">
    <h2 style="margin-top:0;">Налаштування виконання</h2>
    <form method="post" style="display:flex; gap:12px; align-items:flex-end; flex-wrap:wrap;">
        <?php wp_nonce_field('seojusai_queue_settings'); ?>
        <input type="hidden" name="seojusai_queue_settings" value="save" />
        <div>
            <label><strong>Backend</strong></label><br>
            <select name="tasks_backend">
                <option value="as" <?= (get_option('seojusai_tasks_backend', $has_as ? 'as' : 'db') === 'as') ? 'selected' : '' ?>>Дія Scheduler</option>
                <option value="db" <?= (get_option('seojusai_tasks_backend', $has_as ? 'as' : 'db') === 'db') ? 'selected' : '' ?>>DB worker (fallback)</option>
            </select>
        </div>
        <div>
            <label><strong>Concurrency</strong></label><br>
            <input type="number" name="tasks_concurrency" min="1" max="50" value="<?= (int)get_option('seojusai_tasks_concurrency', 5) ?>" />
        </div>
        <div>
            <button class="button button-primary" type="submit">Зберегти</button>
        </div>
    </form>
    <p style="margin:10px 0 0;">Рекомендовано: <strong>Дія Scheduler</strong> + concurrency 5–10. Для слабкого хостингу зменшіть concurrency.</p>
</div>



    <p>
        <strong>Дія Scheduler:</strong>
        <?php if ($has_as): ?>
            <span style="color:#00a32a;">доступний</span>
        <?php else: ?>
            <span style="color:#d63638;">не виявлено</span>
        <?php endif; ?>
        &nbsp;|&nbsp;
        <strong>Concurrency:</strong> <?= (int) get_option('seojusai_tasks_concurrency', 5) ?>
        &nbsp;|&nbsp;
        <strong>Backend:</strong> <?= esc_html(get_option('seojusai_tasks_backend', $has_as ? 'as' : 'db')) ?>
    </p>

    <div style="margin: 12px 0;">
        <?php foreach ($statuses as $k => $label): ?>
            <?php $url = esc_url(add_query_arg(['status' => $k])); ?>
            <a class="button <?= $status === $k ? 'button-primary' : '' ?>" href="<?= $url ?>">
                <?= esc_html($label) ?>
                <?php if ($k !== '' && isset($stat[$k])): ?> (<?= (int)$stat[$k] ?>)<?php endif; ?>
            </a>
        <?php endforeach; ?>
    </div>

    <table class="widefat striped">
        <thead>
            <tr>
                <th style="width:80px;">ID</th>
                <th>Дія</th>
                <th style="width:110px;">Статус</th>
                <th style="width:90px;">Спроби</th>
                <th style="width:160px;">Доступно з</th>
                <th style="width:180px;">Оновлено</th>
                <th style="width:220px;">Остання помилка</th>
                <th style="width:170px;">Дії</th>
            </tr>
        </thead>
        <tbody>
        <?php if (empty($items)): ?>
            <tr><td colspan="8">Немає задач.</td></tr>
        <?php else: ?>
            <?php foreach ($items as $it): ?>
                <tr>
                    <td><?= (int)$it['id'] ?></td>
                    <td>
                        <code><?= esc_html((string)$it['action']) ?></code><br>
                        <small>post_id: <?= (int)$it['post_id'] ?></small>
                    </td>
                    <td><?= esc_html((string)$it['status']) ?></td>
                    <td><?= (int)$it['attempts'] ?> / <?= (int)$it['max_attempts'] ?></td>
                    <td><?= esc_html((string)($it['available_at'] ?? '')) ?></td>
                    <td><?= esc_html((string)($it['updated_at'] ?? '')) ?></td>
                    <td><small><?= esc_html(mb_strimwidth((string)($it['last_error'] ?? ''), 0, 120, '…')) ?></small></td>
                    <td>
                        <form method="post" style="display:flex; gap:6px; align-items:center;">
                            <?php wp_nonce_field('seojusai_queue_action'); ?>
                            <input type="hidden" name="task_id" value="<?= (int)$it['id'] ?>">
                            <button class="button" name="seojusai_queue_action" value="retry">Retry</button>
                            <button class="button button-link-delete" name="seojusai_queue_action" value="delete" onclick="return confirm('Видалити задачу?')">Delete</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
    </table>

    <p style="margin-top:12px;">
        <em>Dead-letter</em> — задачі, які перевищили max_attempts і потребують ручного розгляду.
    </p>
</div>
