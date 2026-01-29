<?php
namespace SEOJusAI\Admin\Ajax;
defined('ABSPATH')||exit;

use SEOJusAI\Queue\TaskQueue;

final class AnalyzeNow {
  public static function handle(): void {
    if (!current_user_can('edit_posts')) wp_send_json_error();
    $postId = (int)($_POST['post_id'] ?? 0);
    if ($postId<=0) wp_send_json_error();
    TaskQueue::enqueue('analyze_post',['post_id'=>$postId]);
    wp_send_json_success(['enqueued'=>true]);
  }
}
