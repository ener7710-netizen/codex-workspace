<?php
declare(strict_types=1);

defined('ABSPATH') || exit;

if ( ! current_user_can('manage_options') ) {
    wp_die(esc_html__('Недостатньо прав доступу.', 'seojusai'));
}

/**
 * Сторінка «Аналіз постів».
 *
 * Виводить розширену таблицю постів із середнім рівнем ризику. Користувач може
 * швидко відфільтрувати записи, що потребують уваги, та перейти до їх
 * редагування. Таблиця обмежується 50 рядками для зручності перегляду.
 */

global $wpdb;
$prefix = $wpdb->prefix;

// Отримуємо список постів з середнім ризиком, сортуємо від найбільшого до найменшого.
// Важливо: таблиця пояснень використовує (entity_type, entity_id), а `risk_level` — рядок.
$posts = $wpdb->get_results(
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
     LIMIT 50",
    ARRAY_A
);

?>
<div class="wrap seojusai-analytics-posts">
    <h1><?php echo esc_html__('Аналіз постів', 'seojusai'); ?></h1>
    <p><?php echo esc_html__('Тут представлений список постів із середнім рівнем ризику. Використовуйте ці дані для визначення пріоритетів оптимізації.', 'seojusai'); ?></p>

    <?php if ( ! empty( $posts ) ) : ?>
        <table class="widefat striped">
            <thead>
                <tr>
                    <th><?php echo esc_html__('ID', 'seojusai'); ?></th>
                    <th><?php echo esc_html__('Запис', 'seojusai'); ?></th>
                    <th><?php echo esc_html__('Середній ризик', 'seojusai'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ( $posts as $row ) :
                    $post_id = (int) $row['post_id'];
                    $avg     = (float) $row['avg_risk'];
                    $title   = get_the_title( $post_id ) ?: esc_html__( 'Без назви', 'seojusai' );
                    $edit_url = get_edit_post_link( $post_id );
                    ?>
                    <tr>
                        <td><code>#<?php echo $post_id; ?></code></td>
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
    <?php else : ?>
        <div class="notice notice-info">
            <p><?php echo esc_html__('Наразі немає даних для відображення аналізу постів.', 'seojusai'); ?></p>
        </div>
    <?php endif; ?>
</div>

<style>
.seojusai-analytics-posts table th,
.seojusai-analytics-posts table td {
    text-align: left;
}
.seojusai-analytics-posts table td {
    vertical-align: top;
}
.seojusai-analytics-posts table td .dashicons {
    margin-left: 4px;
    font-size: 16px;
    vertical-align: text-bottom;
}
</style>
