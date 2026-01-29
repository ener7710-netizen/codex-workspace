<?php
declare(strict_types=1);

use SEOJusAI\Capabilities\CapabilityGuard;
use SEOJusAI\Capabilities\CapabilityMap;
use SEOJusAI\Vectors\VectorStore;
use SEOJusAI\Vectors\VectorRebuilder;
use SEOJusAI\Vectors\VectorRebuildState;
use SEOJusAI\Vectors\VectorNamespaces;
use SEOJusAI\Vectors\VectorVersion;
use SEOJusAI\Input\Input;

defined('ABSPATH') || exit;

if (!CapabilityGuard::can(CapabilityMap::MANAGE_SETTINGS)) {
    wp_die(__('Недостатньо прав доступу.', 'seojusai'));
}

$ns = (Input::get('ns', null) !== null) ? sanitize_key((string)Input::get('ns')) : VectorNamespaces::POSTS;
if (!in_array($ns, [VectorNamespaces::POSTS, VectorNamespaces::EXPLAIN, VectorNamespaces::DEFAULT], true)) {
    $ns = VectorNamespaces::POSTS;
}

$store = new VectorStore();
$version = VectorVersion::current($ns);
$count = $store->count($ns, $version);
$state = VectorRebuildState::get();

if ((Input::post('seojusai_vectors_action', null) !== null)) {
    check_admin_referer('seojusai_vectors_action');
    $act = sanitize_key((string)Input::post('seojusai_vectors_action'));

    if ($act === 'rebuild') {
        $batch = (Input::post('batch_size', null) !== null) ? (int)Input::post('batch_size') : 20;
        $res = VectorRebuilder::start($ns, $batch);
        echo '<div class="notice notice-success is-dismissible"><p>Rebuild запущено. namespace=' . esc_html($ns) . ' version=' . esc_html((string)($res['version'] ?? '')) . '</p></div>';
        $version = VectorVersion::current($ns);
    }

    if ($act === 'purge') {
        $deleted = $store->purge_namespace($ns, null);
        echo '<div class="notice notice-success is-dismissible"><p>Вектори видалено: ' . (int)$deleted . ' (namespace ' . esc_html($ns) . ').</p></div>';
        $count = $store->count($ns, VectorVersion::current($ns));
    }
}

$base_url = menu_page_url('seojusai-vector-memory', false);
?>
<div class="wrap">
    <h1><?php echo esc_html__('Векторна пам\'ять', 'seojusai'); ?></h1>
<?php
$state = \SEOJusAI\Vectors\VectorRebuildState::get();
$indexed = (int) ($state['indexed'] ?? 0);
$offset = (int) ($state['offset'] ?? 0);
$done = !empty($state['done']);
$last = isset($state['last_run_gmt']) ? (string) $state['last_run_gmt'] : '';
?>
<div class="notice notice-info" style="padding:12px 14px; margin-top:12px;">
    <p style="margin:0;"><strong><?php echo esc_html__('Стан перебудови:', 'seojusai'); ?></strong>
        <?php echo $done ? esc_html__('завершено', 'seojusai') : esc_html__('у процесі', 'seojusai'); ?>,
        <?php echo esc_html__('проіндексовано', 'seojusai'); ?>: <?php echo (int) $indexed; ?>,
        <?php echo esc_html__('зсув', 'seojusai'); ?>: <?php echo (int) $offset; ?>
        <?php if ($last !== '') : ?>,
            <?php echo esc_html__('останній запуск (UTC)', 'seojusai'); ?>: <?php echo esc_html($last); ?>
        <?php endif; ?>
    </p>
</div>


    <div class="card" style="max-width:100%; border-left:4px solid #2271b1;">
        <h2 style="margin-top:0;">Стан індексу</h2>
        <p><strong>Namespace:</strong> <code><?= esc_html($ns) ?></code></p>
        <p><strong>Активна версія:</strong> <code><?= (int)$version ?></code></p>
        <p><strong>Кількість векторів (active):</strong> <strong><?= (int)$count ?></strong></p>

        <?php if (!empty($state) && ($state['namespace'] ?? '') === $ns && empty($state['done'])): ?>
            <p><strong>Rebuild:</strong> у процесі</p>
            <ul>
                <li>version: <code><?= (int)($state['version'] ?? 0) ?></code></li>
                <li>offset: <code><?= (int)($state['offset'] ?? 0) ?></code></li>
                <li>indexed batches: <code><?= (int)($state['indexed'] ?? 0) ?></code></li>
                <li>updated: <code><?= esc_html((string)($state['updated_at'] ?? '')) ?></code></li>
            </ul>
            <p><em>Порада:</em> дивіться “Черга задач” для живого прогресу.</p>
        <?php elseif (!empty($state) && ($state['namespace'] ?? '') === $ns && !empty($state['done'])): ?>
            <p><strong>Rebuild:</strong> завершено</p>
            <p>finished: <code><?= esc_html((string)($state['finished_at'] ?? '')) ?></code></p>
        <?php else: ?>
            <p><em>Rebuild зараз не активний.</em></p>
        <?php endif; ?>
    </div>

    <div class="card" style="max-width:100%; margin-top:14px;">
        <h2 style="margin-top:0;">Дії</h2>

        <form method="get" style="display:flex; gap:10px; flex-wrap:wrap; align-items:flex-end;">
            <input type="hidden" name="page" value="seojusai-vector-memory" />
            <div>
                <label><strong>Namespace</strong></label><br>
                <select name="ns">
                    <option value="posts" <?= $ns==='posts'?'selected':'' ?>>posts</option>
                    <option value="explain" <?= $ns==='explain'?'selected':'' ?>>explain</option>
                    <option value="default" <?= $ns==='default'?'selected':'' ?>>default</option>
                </select>
            </div>
            <div><button class="button" type="submit">Перемкнути</button></div>
        </form>

        <hr>

        <form method="post" style="display:flex; gap:10px; flex-wrap:wrap; align-items:flex-end;">
            <?php wp_nonce_field('seojusai_vectors_action'); ?>
            <input type="hidden" name="seojusai_vectors_action" value="rebuild" />
            <div>
                <label><strong><?php echo esc_html__('Розмір пакету', 'seojusai'); ?></strong></label><br>
                <input type="number" name="batch_size" value="20" min="5" max="100" />
            </div>
            <div>
                <button class="button button-primary" type="submit">Rebuild (нова версія)</button>
            </div>
        </form>

        <form method="post" style="margin-top:10px;">
            <?php wp_nonce_field('seojusai_vectors_action'); ?>
            <input type="hidden" name="seojusai_vectors_action" value="purge" />
            <button class="button" type="submit" onclick="return confirm('Видалити всі вектори цього namespace?')">Purge namespace</button>
        </form>

        <p style="margin-top:10px;"><em>Як працює:</em> rebuild піднімає версію (version bump) і будує індекс у фоні через чергу задач. Старі версії видаляються після завершення.</p>
    </div>

</div>
