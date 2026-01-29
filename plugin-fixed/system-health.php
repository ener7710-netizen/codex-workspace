<?php
declare(strict_types=1);

use SEOJusAI\Health\HealthService;
use SEOJusAI\Cache\CacheStats;
use SEOJusAI\Cache\CacheService;
use SEOJusAI\Input\Input;

defined('ABSPATH') || exit;

if (!current_user_can('manage_options')) {
    wp_die(esc_html__('Недостатньо прав доступу.', 'seojusai'));
}

$checks = class_exists(HealthService::class) ? HealthService::checks() : [];
$summary = class_exists(HealthService::class) ? HealthService::summary_status() : 'info';

function seojusai_badge(string $status): string {
    switch ($status) {
        case 'ok':
            return '<span class="badge" style="background:#00a32a;color:#fff;padding:2px 8px;border-radius:999px;font-size:12px;">OK</span>';
        case 'warn':
            return '<span class="badge" style="background:#dba617;color:#111;padding:2px 8px;border-radius:999px;font-size:12px;">Увага</span>';
        case 'fail':
            return '<span class="badge" style="background:#d63638;color:#fff;padding:2px 8px;border-radius:999px;font-size:12px;">Проблема</span>';
        default:
            return '<span class="badge" style="background:#2271b1;color:#fff;padding:2px 8px;border-radius:999px;font-size:12px;">Info</span>';
    }
}
?>

<?php
if ((Input::post('seojusai_cache_action', null) !== null)) {
    check_admin_referer('seojusai_cache_action');
    $act = sanitize_key((string) Input::post('seojusai_cache_action'));
    $ns  = (Input::post('cache_ns', null) !== null) ? sanitize_key((string) Input::post('cache_ns')) : '';
    if ($act === 'purge_all') {
        foreach (['serp','gsc','site_audit','page_audit','explain','vector'] as $n) {
            CacheService::purge_namespace($n);
        }
        CacheStats::reset();
        echo '<div class="notice notice-success is-dismissible"><p>Кеш очищено (усі простори).</p></div>';
    }
    if ($act === 'purge_ns' && $ns) {
        CacheService::purge_namespace($ns);
        echo '<div class="notice notice-success is-dismissible"><p>Кеш очищено: ' . esc_html($ns) . '.</p></div>';
    }
}
?>

<div class="wrap">
    <h1>Стан системи</h1>

    <div class="notice <?php echo $summary === 'fail' ? 'notice-error' : ($summary === 'warn' ? 'notice-warning' : 'notice-success'); ?>">
        <p style="margin: 8px 0;">
            <strong>Підсумок:</strong>
            <?php echo seojusai_badge($summary); ?>
            <?php if ($summary === 'ok'): ?>
                Усе виглядає стабільно.
            <?php elseif ($summary === 'warn'): ?>
                Є попередження — рекомендовано перевірити деталі нижче.
            <?php else: ?>
                Виявлено критичні проблеми — виправте перед продакшеном.
            <?php endif; ?>
        </p>
    </div>

    <div class="card" style="max-width: 100%; margin-top: 16px;">
        <h2 style="margin-top:0;">Перевірки</h2>
        <table class="widefat striped">
            <thead>
                <tr>
                    <th style="width:110px;">Статус</th>
                    <th style="width:260px;">Компонент</th>
                    <th>Деталі</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($checks as $c): ?>
                    <tr>
                        <td><?php echo seojusai_badge((string)($c['status'] ?? 'info')); ?></td>
                        <td><strong><?php echo esc_html((string)($c['title'] ?? '')); ?></strong></td>
                        <td><?php echo wp_kses_post((string)($c['message'] ?? '')); ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (empty($checks)): ?>
                    <tr><td colspan="3">HealthService недоступний.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
        <p style="margin-top:12px;color:#666;">
            Порада: якщо вимкнено WP-Cron, налаштуйте системний cron. Для черг задач рекомендовано Дія Scheduler.
        </p>
    </div>



<div class="card" style="max-width:100%; margin-top:16px;">
    <h2 style="margin-top:0;">Кеш</h2>
    <form method="post" style="display:flex; gap:10px; flex-wrap:wrap; align-items:center;">
        <?php wp_nonce_field('seojusai_cache_action'); ?>
        <button class="button" name="seojusai_cache_action" value="purge_all">Очистити весь кеш</button>
        <select name="cache_ns">
            <option value="serp">serp</option>
            <option value="gsc">gsc</option>
            <option value="site_audit">site_audit</option>
            <option value="page_audit">page_audit</option>
            <option value="explain">explain</option>
            <option value="vector">vector</option>
        </select>
        <button class="button" name="seojusai_cache_action" value="purge_ns">Очистити namespace</button>
    </form>

    <?php $stats = CacheStats::all(); ?>
    <table class="widefat striped" style="margin-top:10px;">
        <thead><tr><th>Namespace</th><th>Transients</th><th>Оновлено</th></tr></thead>
        <tbody>
        <?php foreach (['serp','gsc','site_audit','page_audit','explain','vector'] as $n): ?>
            <?php $st = $stats[$n] ?? ['count' => 0, 'ts' => '—']; ?>
            <tr>
                <td><code><?= esc_html($n) ?></code></td>
                <td><?= (int)($st['count'] ?? 0) ?></td>
                <td><small><?= esc_html((string)($st['ts'] ?? '—')) ?></small></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <p style="margin-top:10px;"><em>Object Cache</em> використовується автоматично, transient — як fallback.</p>
</div>
</div>
