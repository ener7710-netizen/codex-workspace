<?php
declare(strict_types=1);

defined('ABSPATH') || exit;
if (!current_user_can('manage_options')) { return; }

echo '<div class="wrap">';
echo '<h1>' . esc_html__('Мапа сайту (XML Sitemap)', 'seojusai') . '</h1>';
echo '<p>' . esc_html__('SEOJusAI генерує sitemap без сторонніх залежностей (як Rank Math), якщо не активний інший SEO‑плагін.', 'seojusai') . '</p>';

$index = home_url('/sitemap_index.xml');
$pages = home_url('/sitemap-pages.xml');
$posts = home_url('/sitemap-posts.xml');

echo '<p><strong>' . esc_html__('Sitemap index:', 'seojusai') . '</strong> <a href="' . esc_url($index) . '" target="_blank" rel="noopener">' . esc_html($index) . '</a></p>';

echo '<ul style="list-style:disc;padding-left:18px;">';
echo '<li><a href="' . esc_url($pages) . '" target="_blank" rel="noopener">' . esc_html__('Sitemap сторінок (pages)', 'seojusai') . '</a></li>';
echo '<li><a href="' . esc_url($posts) . '" target="_blank" rel="noopener">' . esc_html__('Sitemap записів (posts)', 'seojusai') . '</a></li>';
echo '</ul>';

echo '<p>' . esc_html__('Якщо після активації посилання повертають 404 — перейдіть у Налаштування → Постійні посилання та збережіть їх (оновлення rewrite rules).', 'seojusai') . '</p>';
echo '</div>';
