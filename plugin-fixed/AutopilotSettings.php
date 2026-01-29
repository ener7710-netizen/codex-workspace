<?php
declare(strict_types=1);
defined('ABSPATH')||exit;
add_action('admin_init', function(){
    register_setting('seojusai_autopilot','seojusai_execution_enabled');
    register_setting('seojusai_autopilot','seojusai_autopilot_max_auto_per_minute');
    register_setting('seojusai_autopilot','seojusai_autopilot_max_auto_per_post_hour');
    register_setting('seojusai_autopilot','seojusai_autopilot_fail_burst_threshold');
    register_setting('seojusai_autopilot','seojusai_self_training_enabled');
    register_setting('seojusai_autopilot','seojusai_self_training_max_samples');
    register_setting('seojusai_autopilot','seojusai_api_key');
});
