<?php
namespace SEOJusAI\Admin\Metaboxes;
defined('ABSPATH')||exit;

use SEOJusAI\Repository\DecisionRepository;

final class ProposalsMetabox {

  public static function register(): void {
    add_meta_box(
      'seojusai_proposals',
      'SEOJusAI — Пропозиції',
      [self::class,'render'],
      ['post','page'],
      'side',
      'high'
    );
  }

  public static function render($post): void {
    $decisions = DecisionRepository::getLatestByPost((int)$post->ID, 5);
    ?>
    <div class="seojusai-metabox">
      <p><strong>Статус:</strong> <?php echo $decisions ? 'Проаналізовано' : 'Не проаналізовано'; ?></p>
      <button class="button button-primary" data-post="<?php echo (int)$post->ID; ?>" id="seojusai-analyze-now">Проаналізувати</button>
      <hr/>
      <?php if ($decisions): foreach ($decisions as $d): ?>
        <div class="seojusai-proposal">
          <strong><?php echo esc_html($d->type); ?></strong><br/>
          <small>Впевненість: <?php echo esc_html($d->confidence); ?></small><br/>
          <em><?php echo esc_html($d->explanation); ?></em>
        </div>
      <?php endforeach; else: ?>
        <em>Поки що немає пропозицій.</em>
      <?php endif; ?>
      <p><a href="<?php echo admin_url('admin.php?page=seojusai_decisions&post_id='.$post->ID); ?>">Відкрити центр рішень</a></p>
    
      <?php
        $current_title = get_post_meta($post->ID, '_yoast_wpseo_title', true) ?: get_the_title($post);
        $current_desc  = get_post_meta($post->ID, '_yoast_wpseo_metadesc', true);
      ?>
      <hr/>
      <h4>Попередній перегляд SERP</h4>
      <div class="seojusai-serp">
        <strong><?php echo esc_html($current_title); ?></strong>
        <p><?php echo esc_html($current_desc); ?></p>
      </div>

    </div>
    <?php
  }
}
