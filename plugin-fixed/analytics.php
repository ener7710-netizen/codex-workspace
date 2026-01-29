<?php
declare(strict_types=1);

defined('ABSPATH') || exit;

// Переконаємося, що у користувача є достатньо прав.
if ( ! current_user_can('manage_options') ) {
    wp_die(esc_html__('Недостатньо прав доступу.', 'seojusai'));
}

/**
 * Сторінка «Аналітика».
 *
 * На цій сторінці відображаються основні агреговані метрики з бази даних
 * плагіну: середній рівень ризику за всіма обʼєктами, кількість виконаних
 * дій ШІ по типах та топ-5 постів з найбільшим середнім ризиком. Ці дані
 * допомагають швидко оцінити, наскільки “здоровий” контент вашого сайту та
 * які напрями потребують уваги.
 */

global $wpdb;

$prefix = $wpdb->prefix;

// Обчислюємо середній рівень ризику на основі таблиці пояснень.
// Важливо: у `seojusai_explanations` немає `post_id` — використовується пара (entity_type, entity_id).
// Також `risk_level` зберігається як рядок (low/medium/high/critical/...), тому нормалізуємо його у шкалу 0..4.
$avg_risk = (float) $wpdb->get_var(
    "SELECT AVG(
            CASE risk_level
                WHEN 'low' THEN 1
                WHEN 'medium' THEN 2
                WHEN 'high' THEN 3
                WHEN 'critical' THEN 4
                WHEN 'severe' THEN 4
                WHEN 'unknown' THEN 0
                ELSE 0
            END
        )
     FROM {$prefix}seojusai_explanations
     WHERE risk_level IS NOT NULL
       AND entity_id > 0
       AND entity_type IN ('post','page')"
);

// Отримуємо розподіл дій за типами з таблиці впливу.
$impact_counts = $wpdb->get_results(
    "SELECT action_type, COUNT(*) AS cnt FROM {$prefix}seojusai_impact GROUP BY action_type",
    ARRAY_A
);

// Будуємо асоціативний масив для зручного доступу.
$actions_summary = [];
if ( ! empty( $impact_counts ) ) {
    foreach ( $impact_counts as $row ) {
        $actions_summary[ (string) $row['action_type'] ] = (int) $row['cnt'];
    }
}

// Отримуємо топ-5 постів за середнім рівнем ризику.
// Примітка: `entity_id` — це ID поста/сторінки у WordPress.
$top_posts = $wpdb->get_results(
    "SELECT entity_id AS post_id,
            AVG(
                CASE risk_level
                    WHEN 'low' THEN 1
                    WHEN 'medium' THEN 2
                    WHEN 'high' THEN 3
                    WHEN 'critical' THEN 4
                    WHEN 'severe' THEN 4
                    WHEN 'unknown' THEN 0
                    ELSE 0
                END
            ) AS avg_risk
     FROM {$prefix}seojusai_explanations
     WHERE entity_id > 0
       AND entity_type IN ('post','page')
       AND risk_level IS NOT NULL
     GROUP BY entity_id
     ORDER BY avg_risk DESC
     LIMIT 5",
    ARRAY_A
);

// Виведення сторінки.
?>
<div class="wrap seojusai-analytics">
    <h1><?php echo esc_html__('Аналітика', 'seojusai'); ?></h1>

    <div class="seojusai-card">
        <h2><?php echo esc_html__('Загальні метрики', 'seojusai'); ?></h2>
        <p><?php echo esc_html__('Ці показники дають загальне уявлення про стан контенту та активність ШІ.', 'seojusai'); ?></p>

        <div class="seojusai-stats-grid">
            <div class="seojusai-stat-item">
                <span class="seojusai-stat-value">
                    <?php echo esc_html( number_format_i18n( $avg_risk, 2 ) ); ?>
                </span>
                <span class="seojusai-stat-label">
                    <?php echo esc_html__('Середній рівень ризику', 'seojusai'); ?>
                </span>
            </div>
            <?php
            // Виводимо кількість дій для найбільш популярних типів.
            $types = [
                'update'    => __('Оновлення', 'seojusai'),
                'rollback'  => __('Відкат', 'seojusai'),
                'new_page'  => __('Нова сторінка', 'seojusai'),
            ];
            foreach ( $types as $key => $label ) :
                $count = $actions_summary[ $key ] ?? 0;
                ?>
                <div class="seojusai-stat-item">
                    <span class="seojusai-stat-value"><?php echo (int) $count; ?></span>
                    <span class="seojusai-stat-label"><?php echo esc_html( $label ); ?></span>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <?php if ( ! empty( $top_posts ) ) : ?>
        <div class="seojusai-card" style="margin-top:20px;">
            <h2><?php echo esc_html__('Топ-5 постів за ризиком', 'seojusai'); ?></h2>
            <table class="widefat striped">
                <thead>
                    <tr>
                        <th><?php echo esc_html__('Запис', 'seojusai'); ?></th>
                        <th><?php echo esc_html__('Середній ризик', 'seojusai'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ( $top_posts as $row ) :
                        $post_id  = (int) $row['post_id'];
                        $avg      = (float) $row['avg_risk'];
                        $title    = get_the_title( $post_id ) ?: esc_html__( 'Без назви', 'seojusai' );
                        $edit_url = get_edit_post_link( $post_id );
                        ?>
                        <tr>
                            <td>
                                <strong><?php echo esc_html( $title ); ?></strong>
                                <?php if ( $edit_url ) : ?>
                                    <a href="<?php echo esc_url( $edit_url ); ?>" class="button-link"><span class="dashicons dashicons-edit"></span></a>
                                <?php endif; ?>
                            </td>
                            <td><?php echo esc_html( number_format_i18n( $avg, 2 ) ); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php else : ?>
        <div class="seojusai-card" style="margin-top:20px;">
            <p><?php echo esc_html__('Наразі немає достатньо даних для побудови рейтингу постів.', 'seojusai'); ?></p>
        </div>
    <?php endif; ?>
</div>

<style>
.seojusai-analytics .seojusai-stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
    gap: 20px;
    margin-top: 15px;
}
.seojusai-analytics .seojusai-stat-item {
    text-align: center;
    background: #f8f9fa;
    border: 1px solid #e0e0e0;
    padding: 15px;
    border-radius: 8px;
}
.seojusai-analytics .seojusai-stat-value {
    display: block;
    font-size: 24px;
    font-weight: 600;
    color: #2271b1;
}
.seojusai-analytics .seojusai-stat-label {
    display: block;
    font-size: 12px;
    color: #555d66;
    margin-top: 4px;
    text-transform: uppercase;
}
</style>
