<?php
declare(strict_types=1);

namespace SEOJusAI\Sitemap;

use SEOJusAI\Compat\SeoEnvironmentDetector;

defined('ABSPATH') || exit;

/**
 * SitemapController (native, no dependencies)
 *
 * Політика конфліктів:
 * - якщо активний інший SEO-плагін (Yoast/Rank Math/AIOSEO) — не реєструємо URL, щоб не перехоплювати sitemap.
 *
 * URL:
 * - /sitemap_index.xml
 * - /sitemap-pages.xml
 * - /sitemap-posts.xml
 */
final class SitemapController {

    private const QV = 'seojusai_sitemap';

    public function register(): void {
        // Якщо активний інший SEO-плагін — не втручаємось
        if (SeoEnvironmentDetector::is_any_seo_active()) {
            return;
        }

        add_filter('query_vars', [$this, 'register_query_vars']);
        add_action('init', [$this, 'register_rewrites'], 5);
        add_action('template_redirect', [$this, 'maybe_render'], 0);

        // Виключаємо noindex записи з нашого sitemap
        add_filter('seojusai_sitemap_posts_query_args', [$this, 'filter_posts_query'], 10, 2);
    }

    /**
     * @param array<int,string> $vars
     * @return array<int,string>
     */
    public function register_query_vars(array $vars): array {
        $vars[] = self::QV;
        return $vars;
    }

    public function register_rewrites(): void {
        add_rewrite_rule('sitemap_index\.xml$', 'index.php?' . self::QV . '=index', 'top');
        add_rewrite_rule('sitemap-(pages|posts)\.xml$', 'index.php?' . self::QV . '=$matches[1]', 'top');
    }

    public function maybe_render(): void {
        $type = (string) get_query_var(self::QV, '');
        if ($type === '') {
            return;
        }

        nocache_headers();
        header('Content-Type: application/xml; charset=UTF-8', true);

        if ($type === 'index') {
            echo $this->render_index();
            exit;
        }

        if ($type === 'pages' || $type === 'posts') {
            echo $this->render_post_type($type === 'pages' ? 'page' : 'post');
            exit;
        }

        // Unknown
        status_header(404);
        echo '<?xml version="1.0" encoding="UTF-8"?><error>Not found</error>';
        exit;
    }

    private function render_index(): string {
        $base = home_url('/');
        $items = [
            [
                'loc' => home_url('/sitemap-pages.xml'),
                'lastmod' => gmdate('c'),
            ],
            [
                'loc' => home_url('/sitemap-posts.xml'),
                'lastmod' => gmdate('c'),
            ],
        ];

        $xml  = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
        foreach ($items as $item) {
            $xml .= '  <sitemap>' . "\n";
            $xml .= '    <loc>' . $this->xml($item['loc']) . '</loc>' . "\n";
            $xml .= '    <lastmod>' . $this->xml($item['lastmod']) . '</lastmod>' . "\n";
            $xml .= '  </sitemap>' . "\n";
        }
        $xml .= '</sitemapindex>';
        return $xml;
    }

    private function render_post_type(string $post_type): string {
        $args = [
            'post_type'              => $post_type,
            'post_status'            => 'publish',
            'posts_per_page'         => 2000,
            'orderby'                => 'modified',
            'order'                  => 'DESC',
            'no_found_rows'          => true,
            'ignore_sticky_posts'    => true,
            'update_post_meta_cache' => false,
            'update_post_term_cache' => false,
            'fields'                 => 'ids',
        ];

        /**
         * @var array<string,mixed> $args
         */
        $args = apply_filters('seojusai_sitemap_posts_query_args', $args, $post_type);

        $ids = get_posts($args);

        $xml  = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

        foreach ($ids as $id) {
            $loc = get_permalink((int) $id);
            if (!is_string($loc) || $loc === '') {
                continue;
            }
            $lastmod = get_post_modified_time('c', true, (int) $id);
            if (!is_string($lastmod) || $lastmod === '') {
                $lastmod = gmdate('c');
            }
            $xml .= '  <url>' . "\n";
            $xml .= '    <loc>' . $this->xml($loc) . '</loc>' . "\n";
            $xml .= '    <lastmod>' . $this->xml($lastmod) . '</lastmod>' . "\n";
            $xml .= '  </url>' . "\n";
        }

        $xml .= '</urlset>';
        return $xml;
    }

    private function xml(string $value): string {
        return htmlspecialchars($value, ENT_QUOTES | ENT_XML1, 'UTF-8');
    }

    /**
     * @param array<string,mixed> $args
     * @param string $post_type
     * @return array<string,mixed>
     */
    public function filter_posts_query(array $args, string $post_type): array {
        $args['meta_query'] = $args['meta_query'] ?? [];
        $args['meta_query'][] = [
            'relation' => 'OR',
            [
                'key'     => '_seojusai_robots',
                'compare' => 'NOT EXISTS',
            ],
            [
                'key'     => '_seojusai_robots',
                'value'   => 'noindex',
                'compare' => 'NOT LIKE',
            ],
        ];
        return $args;
    }
}
