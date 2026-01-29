<?php
defined('ABSPATH')||exit;
use SEOJusAI\Repository\DecisionRepository;
use SEOJusAI\Repository\SeoMetaRepository;
use SEOJusAI\Input\Input;


// Ensure only users with manage_options can access this page
if (!current_user_can('manage_options')) {
    wp_die(esc_html__('Недостатньо прав доступу.', 'seojusai'), 403);
}

// Sanitize incoming filter parameters using helper methods
$postId    = Input::get_int('post_id', 0);
$status    = Input::get_key('status', '');
$confidence = Input::get_text('confidence', '');

$items = DecisionRepository::filter([
    'post_id'    => $postId,
    'status'     => $status,
    'confidence' => $confidence,
]);
?>
<div class="wrap">
<h1>SEOJusAI — Центр рішень</h1>

<form method="get">
  <input type="hidden" name="page" value="seojusai_decisions"/>
  <label>Статус:
    <select name="status">
      <option value="">Будь-який</option>
      <option value="planned">Заплановано</option>
      <option value="approved">Підтверджено</option>
      <option value="rejected">Відхилено</option>
    </select>
  </label>
  <label>Confidence ≥
    <input name="confidence" value="<?php echo esc_attr($confidence); ?>" size="4"/>
  </label>
  <button class="button">Фільтрувати</button>
</form>

<table class="widefat striped">
<thead><tr>
  <th>ID</th><th>Post</th><th>Type</th><th>Confidence</th><th>Status</th><th>Дії</th>
</tr></thead>
<tbody>
<?php foreach ($items as $i): $m = isset($i->decision_hash)? SeoMetaRepository::get_by_decision($i->decision_hash): null; ?>
<tr>
  <td><?php echo (int)$i->id; ?></td>
  <td><?php echo (int)$i->post_id; ?></td>
  <td><?php echo esc_html($i->type); ?></td>
  <td><?php echo esc_html($i->confidence); ?></td>
  <td><?php echo esc_html($i->status); ?></td>
  <td>
    <details><summary>Перегляд змін</summary>
      <div style="background:#f9f9f9;padding:8px;margin:6px 0;">
        <strong>Current:</strong><br/>
        <?php echo esc_html(get_post_meta($i->post_id,'_yoast_wpseo_title',true)); ?><br/>
        <?php echo esc_html(get_post_meta($i->post_id,'_yoast_wpseo_metadesc',true)); ?>
        <hr/>
        <strong>Proposed:</strong><br/>
        <?php echo esc_html($m->seo_title ?? ''); ?><br/>
        <?php echo esc_html($m->meta_description ?? ''); ?>
      </div>
    </details>
    <?php
    // Build action links with nonce for CSRF protection. Use admin-post.php endpoints.
    $approve_link = wp_nonce_url(
        admin_url('admin-post.php?action=seojusai_decision_approve&decision_hash=' . urlencode($i->decision_hash)),
        'seojusai_decision_action',
        '_wpnonce'
    );
    $reject_link = wp_nonce_url(
        admin_url('admin-post.php?action=seojusai_decision_reject&decision_hash=' . urlencode($i->decision_hash)),
        'seojusai_decision_action',
        '_wpnonce'
    );
    ?>
    <a href="<?php echo esc_url($approve_link); ?>">Підтвердити</a> |
    <a href="<?php echo esc_url($reject_link); ?>">Відхилити</a>
  </td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</div>
