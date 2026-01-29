<?php
/**
 * @boundary Execution is controlled exclusively by AutopilotExecutionLoop.
 * ❌ Manual execution disabled — Autopilot only.
 */

if (!defined('ABSPATH')) { exit; }

?>
<div class="wrap">
    <h1><?php echo esc_html__('Масовий аудит', 'seojusai'); ?></h1>
    <p><?php echo esc_html__('Ручний запуск аудиту вимкнено. Виконання можливе лише через AutopilotExecutionLoop.', 'seojusai'); ?></p>
</div>
