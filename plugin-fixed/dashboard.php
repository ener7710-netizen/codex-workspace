<?php
declare(strict_types=1);

defined('ABSPATH') || exit;

global $wpdb;

/**
 * Отримуємо статистику з урахуванням нових таблиць
 */
$count_snapshots = (int) @$wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}seojusai_snapshots");
$count_kbe       = (int) @$wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}seojusai_kbe");

// Середній бал SEO по сайту (з таблиці explanations, якщо ми туди пишемо score)
$avg_score = (int) @$wpdb->get_var("
    SELECT AVG(CAST(risk_level AS UNSIGNED))
    FROM {$wpdb->prefix}seojusai_explanations
    WHERE source = 'ai_score'
") ?: 0;

$last_impact = @$wpdb->get_results("
    SELECT * FROM {$wpdb->prefix}seojusai_impact
    ORDER BY created_at DESC LIMIT 7
");

$is_emergency = get_option('seojusai_emergency_stop', false);
?>

<div class="wrap seojusai-dashboard">
    <div class="seojusai-header">
        <h1>SEOJusAI — SEO Autopilot 2026</h1>
        <p class="description">Центральна панель управління вашим інтелектуальним SEO-активом.</p>
    </div>

    <div class="seojusai-stats-grid">
        <div class="seojusai-card stat-status">
            <div class="card-icon"><span class="dashicons dashicons-performance"></span></div>
            <div class="card-content">
                <h3>Стан Автопілота</h3>
                <?php if ($is_emergency): ?>
                    <div class="status-badge error">EMERGENCY STOP АКТИВНИЙ</div>
                    <p>AI-операції призупинено. Перевірте критичні зауваження в Базі Знань.</p>
                <?php else: ?>
                    <div class="status-badge ok">Система в нормі</div>
                    <p>ШІ аналізує контент та готовий до покращення ранжування.</p>
                <?php endif; ?>
            </div>
        </div>

        <div class="seojusai-card stat-kbe">
            <div class="card-icon"><span class="dashicons dashicons-welcome-learn-more"></span></div>
            <div class="card-content">
                <h3>Інтелект та Навчання</h3>
                <div class="stat-row">
                    <span>Засвоєно уроків (KBE):</span>
                    <strong class="highlight-blue"><?= $count_kbe ?></strong>
                </div>
                <div class="stat-row">
                    <span>Збережено версій (Snapshots):</span>
                    <strong><?= $count_snapshots ?></strong>
                </div>
            </div>
        </div>
    </div>

    <div class="seojusai-card main-table-card">
        <div class="card-header-flex">
            <h3>Журнал впливу (Impact Log)</h3>
            <span class="version-tag">DB v.<?= esc_html(get_option('seojusai_db_version', '1.0.0')) ?></span>
        </div>

        <?php if ($last_impact && count($last_impact) > 0): ?>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th style="width: 15%;">ID Об'єкта</th>
                        <th style="width: 25%;">Дія AI</th>
                        <th>Сторінка</th>
                        <th style="width: 20%;">Час події</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($last_impact as $log): ?>
                    <tr>
                        <td><code>#<?= (int)$log->post_id ?></code></td>
                        <td>
                            <span class="type-tag tag-<?= esc_attr($log->action_type) ?>">
                                <?= esc_html($log->action_type) ?>
                            </span>
                        </td>
                        <td>
                            <strong><?= get_the_title($log->post_id) ?: 'Без назви' ?></strong>
                            <a href="<?= get_edit_post_link($log->post_id) ?>" class="view-link">
                                <span class="dashicons dashicons-edit"></span>
                            </a>
                        </td>
                        <td><?= date_i18n('j M Y, H:i', strtotime($log->created_at)) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <div class="seojusai-empty-state">
                <span class="dashicons dashicons-info"></span>
                <p>Журнал поки що порожній. Запустіть аналіз у редакторі сторінки, щоб побачити результати.</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
/**
 * AJAX обробка створення сторінок за рекомендацією AI
 */
document.addEventListener('DOMContentLoaded', function() {
    // Делегування події на весь документ (працюватиме навіть для динамічно доданих кнопок)
    document.body.addEventListener('click', function(e) {
        const btn = e.target.closest('.seojusai-btn-create-page');
        if (!btn) return;

        e.preventDefault();

        const title = btn.dataset.title;
        const reason = btn.dataset.reason;

        if (!confirm(`Підтвердити створення чернетки сторінки: "${title}"?`)) {
            return;
        }

        btn.disabled = true;
        const originalText = btn.innerHTML;
        btn.innerHTML = '<span class="spinner is-active" style="visibility:visible; float:none; margin:0 5px 0 0;"></span> Створюю...';

        const data = new URLSearchParams();
        data.append('action', 'seojusai_create_page');
        data.append('nonce', '<?php echo wp_create_nonce("seojusai_admin_nonce"); ?>');
        data.append('title', title);
        data.append('reason', reason);

        fetch(ajaxurl, {
            method: 'POST',
            body: data,
            headers: { 'Content-Type' => 'application/x-www-form-urlencoded' }
        })
        .then(response => response.json())
        .then(res => {
            if (res.success) {
                btn.innerHTML = '✅ Створено';
                btn.classList.remove('button-primary');
                btn.classList.add('button-disabled');

                // Додаємо посилання на редагування поруч із кнопкою
                if (res.data && res.data.edit_url) {
                    const editLink = document.createElement('a');
                    editLink.href = res.data.edit_url;
                    editLink.target = '_blank';
                    editLink.className = 'button button-secondary';
                    editLink.style.marginLeft = '10px';
                    editLink.innerText = 'Відкрити редактор';
                    btn.parentNode.appendChild(editLink);
                }
            } else {
                alert('Помилка: ' + (res.data.message || 'Невідома помилка'));
                btn.disabled = false;
                btn.innerHTML = originalText;
            }
        })
        .catch(err => {
            console.error('AJAX Error:', err);
            btn.disabled = false;
            btn.innerHTML = 'Помилка мережі';
        });
    });
});
</script>

<style>
.seojusai-dashboard { margin-top: 20px; max-width: 1100px; font-family: -apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Oxygen-Sans,Ubuntu,Cantarell,"Helvetica Neue",sans-serif; }
.seojusai-header h1 { font-size: 28px; font-weight: 700; color: #1d2327; margin: 0; }
.seojusai-stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px; margin: 25px 0; }
.seojusai-card { background: #fff; border: 1px solid #dcdcde; border-radius: 12px; padding: 24px; box-shadow: 0 2px 4px rgba(0,0,0,0.03); }
.stat-status { display: flex; align-items: center; gap: 20px; }
.highlight-blue { color: #2271b1; }
.card-header-flex { display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; }
.version-tag { font-size: 10px; background: #f0f0f1; color: #646970; padding: 2px 6px; border-radius: 4px; }
.status-badge { display: inline-block; padding: 6px 14px; border-radius: 20px; font-weight: 600; font-size: 11px; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 12px; }
.status-badge.ok { background: #dcfce7; color: #166534; }
.status-badge.error { background: #fee2e2; color: #991b1b; }
.stat-row { display: flex; justify-content: space-between; padding: 10px 0; border-bottom: 1px solid #f0f0f1; }
.stat-row:last-child { border-bottom: none; }
.type-tag { background: #f0f6fc; color: #0c4a6e; padding: 4px 10px; border-radius: 6px; font-size: 11px; font-weight: 500; }
.tag-update { background: #f0fdf4; color: #166534; }
.tag-rollback { background: #fff7ed; color: #9a3412; }
.tag-new_page { background: #e0f2fe; color: #0369a1; } /* Колір для нової дії */
.view-link { text-decoration: none; color: #2271b1; margin-left: 5px; }
.view-link .dashicons { font-size: 16px; width: 16px; height: 16px; vertical-align: middle; }
.seojusai-empty-state { text-align: center; padding: 60px; }
.seojusai-empty-state .dashicons { font-size: 64px; width: 64px; height: 64px; margin-bottom: 15px; color: #dcdcde; }

/* Стилі для кнопок у завданнях ШІ */
.seojusai-btn-create-page {
    margin-top: 5px;
    cursor: pointer;
}
</style>
