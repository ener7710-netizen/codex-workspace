<?php
declare(strict_types=1);

namespace SEOJusAI\Tasks;

use SEOJusAI\Core\EmergencyStop;
use SEOJusAI\Impact\ImpactAnalyzer;
use SEOJusAI\Learning\LearningRepository;
use SEOJusAI\Bulk\BulkJobRepository;
use SEOJusAI\Analyze\PageAuditRunner;
use SEOJusAI\Analyze\Intent\IntentClassifier;
use SEOJusAI\Analyze\Intent\CannibalizationDetector;
use SEOJusAI\Crawl\PageVsSerpAnalyzer;
use SEOJusAI\Meta\MetaRepository;
use SEOJusAI\Snapshots\SnapshotService;
use SEOJusAI\Snapshots\SnapshotRepository;
use SEOJusAI\Tasks\TaskQueue;
use SEOJusAI\Learning\LearningLoop;
use SEOJusAI\Vectors\VectorVersion;
use SEOJusAI\Vectors\VectorNamespaces;
use SEOJusAI\Vectors\VectorIndexManager;
use SEOJusAI\Vectors\VectorRebuildState;
use SEOJusAI\Vectors\VectorRebuilder;

defined('ABSPATH') || exit;

final class TaskExecutors {

	private static bool $registered = false;

	public static function register(): void {
		if ( self::$registered ) return;
		self::$registered = true;

		add_filter('seojusai/tasks/execute', [ self::class, 'handle' ], 10, 4);
	}

	public static function handle(bool $result, string $type, array $payload, array $task): bool {
		if ( EmergencyStop::is_active() ) return false;

		$post_id = (int) ($payload['post_id'] ?? 0);
		$before = $post_id > 0 ? self::capture_state($post_id) : [];

		$ok = match ($type) {
			'add_section'       => self::add_content_section($payload),
			'add_internal_link' => self::add_internal_link($payload),
			'add_schema'        => self::update_schema($payload),
			'page_audit'        => self::run_page_audit($payload),
			'apply_recommendations' => self::apply_recommendations($payload),
			'rollback_last'     => self::rollback_last($payload),
			'serp_snapshot'     => self::run_serp_snapshot($payload),
			'draft_from_competitors' => self::create_draft_from_competitors($payload),
			'pagespeed_snapshot'=> self::run_pagespeed_snapshot($payload),
			'market_refresh'    => self::run_market_refresh($payload),
			'intent_cannibal_audit' => self::run_intent_cannibal_audit($payload),
			'vectors/rebuild_batch' => self::run_vectors_rebuild_batch($payload),
			'vectors/index_post'    => self::run_vectors_index_post($payload),
			'learning/run_weekly'   => self::run_learning_run_weekly($payload),
default             => false,
		};

		if ($ok && $post_id > 0) {
			$after = self::capture_state($post_id);
			// Impact log
			if (class_exists(ImpactAnalyzer::class)) {
				(new ImpactAnalyzer())->record('apply', 'post', $post_id, $before, $after, [
					'task_id' => (int)($task['id'] ?? 0),
					'task_type' => $type,
					'decision_hash' => (string)($task['decision_hash'] ?? ''),
				]);
			}

			// Learning row (predicted values can be attached by AI/Opportunity)
			if (class_exists(LearningRepository::class)) {
				(new LearningRepository())->insert([
					'entity_type' => 'post',
					'entity_id' => $post_id,
					'decision_hash' => (string)($task['decision_hash'] ?? ''),
					'predicted_impact' => (float)($payload['predicted_impact'] ?? 0),
					'predicted_effort' => (float)($payload['predicted_effort'] ?? 0),
					'observed_clicks_delta' => 0,
					'observed_pos_delta' => 0,
					'observed_impressions_delta' => 0,
					'window_start' => null,
					'window_end' => null,
				]);
			}
		}

		self::maybe_bump_bulk($payload, $ok);
		return $ok;
	}

	/**
	 * Додає новий заголовок (H2-H4) в кінець контенту як плейсхолдер для юриста.
	 */
	private static function add_content_section(array $p): bool {
		$post_id = (int) ($p['post_id'] ?? 0);
		$level   = sanitize_key($p['level'] ?? 'h2');
		$title   = sanitize_text_field($p['title'] ?? '');

		if ( $post_id <= 0 || $title === '' ) return false;

		$content = get_post_field('post_content', $post_id);

		// Перевірка на дублікат заголовка
		if ( str_contains($content, $title) ) return true;

		$new_block = "\n\n";
		$new_block .= "<{$level}>" . esc_html($title) . "</{$level}>\n";
		$new_block .= "\n";
		$new_block .= "\n<p>ШІ рекомендує розкрити цю тему для покращення релевантності...</p>\n\n";

		return (bool) wp_update_post([
			'ID'           => $post_id,
			'post_content' => $content . $new_block,
		]);
	}

	private static function add_internal_link(array $p): bool {
		$post_id = (int) ($p['post_id'] ?? 0);
		$anchor  = sanitize_text_field($p['anchor'] ?? '');
		$url     = esc_url_raw($p['url'] ?? '');

		if ( $post_id <= 0 || !$anchor || !$url ) return false;

		$content = get_post_field('post_content', $post_id);
		$link = sprintf('<a href="%s">%s</a>', $url, $anchor);

		// Додаємо лінк в перший знайдений параграф
		$updated = preg_replace('/<\/p>/', " {$link}</p>", $content, 1);

		return (bool) wp_update_post(['ID' => $post_id, 'post_content' => $updated]);
	}

	private static function update_schema(array $p): bool {
		$post_id = (int) ($p['post_id'] ?? 0);
		$type    = sanitize_text_field($p['type'] ?? '');
		if ($post_id <= 0 || !$type) return false;

		$current = get_post_meta($post_id, '_seojusai_schema_types', true) ?: [];
		$current[] = $type;

		return (bool) update_post_meta($post_id, '_seojusai_schema_types', array_unique($current));
	}



    	private static function create_draft_from_competitors(array $payload): bool {
    		$post_id = (int) ($payload['post_id'] ?? 0);
    		if ($post_id <= 0) {
    			return false;
    		}

    		$src = get_post($post_id);
    		if (!$src) {
    			return false;
    		}

    		// Build competitor structure via SERP fingerprints (no text copying).
    		$query = (string) get_post_meta($post_id, '_seojusai_keyword', true);
    		if (trim($query) === '') {
    			$query = (string) ($src->post_title ?? '');
    		}

    		$analyzer = new PageVsSerpAnalyzer();
    		$compare = $analyzer->compare([
    			'url' => (string) get_permalink($post_id),
    			'h1'  => (string) ($src->post_title ?? ''),
    			'h2'  => [],
    			'schema' => [],
    			'query' => $query,
    		]);

    		$structure = (array) ($compare['serp_structure'] ?? []);
    		$schema_pool = (array) ($compare['schema_pool'] ?? []);

    		$outline = [];
    		foreach ($structure as $item) {
    			$label = '';
    			if (is_string($item)) {
    				$label = $item;
    			} elseif (is_array($item)) {
    				$label = (string) ($item['title'] ?? $item['label'] ?? $item['h2'] ?? '');
    			}
    			$label = trim(wp_strip_all_tags($label));
    			if ($label !== '') {
    				$outline[] = $label;
    			}
    			if (count($outline) >= 10) {
    				break;
    			}
    		}
    		$outline = array_values(array_unique($outline));
    		if (empty($outline)) {
    			$outline = [__('Огляд теми', 'seojusai'), __('Практика та ризики', 'seojusai'), __('Питання та відповіді', 'seojusai')];
    		}

    		// Create draft as a sibling copy (safe, no overwrite).
    		$new_post = [
    			'post_type'    => $src->post_type,
    			'post_status'  => 'draft',
    			'post_parent'  => (int) ($src->post_parent ?? 0),
    			'post_title'   => sprintf(__('Чернетка: %s', 'seojusai'), (string) ($src->post_title ?: ('#' . $post_id))),
    			'post_content' => self::build_draft_blocks((string) ($src->post_title ?? ''), $outline, $schema_pool),
    		];

    		$new_id = wp_insert_post(wp_slash($new_post), true);
    		if (is_wp_error($new_id) || (int) $new_id <= 0) {
    			return false;
    		}

    		// Minimal SEO meta placeholders (user can refine in editor).
    		$meta_repo = new MetaRepository();
    		$meta_repo->save((int) $new_id, [
    			'title' => (string) ($src->post_title ?? ''),
    			'description' => '',
    			'canonical' => '',
    			'robots' => 'index,follow',
    		]);

    		update_post_meta((int) $new_id, '_seojusai_draft_source', (int) $post_id);
    		update_post_meta((int) $new_id, '_seojusai_draft_query', $query);
    		update_post_meta((int) $new_id, '_seojusai_draft_outline', wp_json_encode($outline));
    		update_post_meta((int) $new_id, '_seojusai_draft_schema_pool', wp_json_encode(array_values(array_unique(array_map('strval', $schema_pool)))));

    		return true;
    	}

    	private static function build_draft_blocks(string $title, array $outline, array $schema_pool): string {
    		$blocks = [];

    		$blocks[] = '<!-- wp:paragraph -->' . "
" .
    			'<p>' . esc_html__('Ця чернетка сформована SEOJusAI на основі структури конкурентів у SERP. Перевірте юридичну точність і адаптуйте під вашу практику.', 'seojusai') . '</p>' . "
" .
    			'<!-- /wp:paragraph -->';

    		foreach ($outline as $h2) {
    			$blocks[] = '<!-- wp:heading {"level":2} -->' . "
" .
    				'<h2>' . esc_html($h2) . '</h2>' . "
" .
    				'<!-- /wp:heading -->';
    			$blocks[] = '<!-- wp:paragraph -->' . "
" .
    				'<p>' . esc_html__('Додайте зміст розділу, приклади з практики та посилання на норми/постанови. Уникайте копіювання конкурентів.', 'seojusai') . '</p>' . "
" .
    				'<!-- /wp:paragraph -->';
    		}

    		$schema_types = array_map('strtolower', array_map('strval', $schema_pool));
    		if (in_array('faqpage', $schema_types, true) || in_array('faq', $schema_types, true)) {
    			$blocks[] = '<!-- wp:heading {"level":2} -->' . "
" .
    				'<h2>' . esc_html__('Питання та відповіді', 'seojusai') . '</h2>' . "
" .
    				'<!-- /wp:heading -->';
    			$blocks[] = '<!-- wp:list -->' . "
" .
    				'<ul><li>' . esc_html__('Питання 1…', 'seojusai') . '</li><li>' . esc_html__('Питання 2…', 'seojusai') . '</li></ul>' . "
" .
    				'<!-- /wp:list -->';
    		}

    		return implode("

", $blocks) . "
";
    	}

private static function maybe_bump_bulk(array $payload, bool $ok): void {
	$bulk_id = (int)($payload['bulk_job_id'] ?? 0);
	if ($bulk_id <= 0) return;
	try { (new BulkJobRepository())->bump($bulk_id, $ok); } catch (\Throwable $e) {}
}

private static function run_page_audit(array $payload): bool {
		$post_id = (int) ($payload['post_id'] ?? 0);
		if ($post_id <= 0) {
			return false;
		}

		try {
			// UI/Editor relies on stored summary + score meta. Ensure they are refreshed.
			if (class_exists(\SEOJusAI\Tasks\AuditPostJob::class)) {
				\SEOJusAI\Tasks\AuditPostJob::run($post_id);
			}

			// Persist full analysis snapshot for explain/impact layers.
			if (class_exists(PageAuditRunner::class)) {
				PageAuditRunner::run($post_id);
			}

			return true;
		} catch (\Throwable $e) {
			return false;
		}
	}

private static function apply_recommendations(array $payload): bool {
	$post_id = (int) ($payload['post_id'] ?? 0);
	if ($post_id <= 0) return false;

	$ok = false;
	$proposals = apply_filters('seojusai/proposals/for_post', [], $post_id);
	if (!is_array($proposals)) $proposals = [];

	foreach ($proposals as $p) {
		if (!is_array($p)) continue;
		$type = (string)($p['type'] ?? '');
		$data = (array)($p['payload'] ?? []);
		$data['post_id'] = $post_id;

		if (!in_array($type, ['add_section','add_internal_link','add_schema'], true)) continue;

		$one = match ($type) {
			'add_section' => self::add_content_section($data),
			'add_internal_link' => self::add_internal_link($data),
			'add_schema' => self::update_schema($data),
			default => false,
		};
		$ok = $ok || (bool) $one;
	}

	return $ok;
}

private static function rollback_last(array $payload): bool {
	$post_id = (int) ($payload['post_id'] ?? 0);
	if ($post_id <= 0 || !class_exists(SnapshotService::class)) return false;
	try {
		$service = new SnapshotService();
		$snapshot_id = $service->repo()->get_latest_post_snapshot_id($post_id);
		if ($snapshot_id <= 0) return false;
		$res = $service->restore_post_snapshot($snapshot_id);
		return !is_wp_error($res);
	} catch (\Throwable $e) {
		return false;
	}
}



	private static function run_serp_snapshot(array $payload): bool {
		$query = isset($payload['query']) ? sanitize_text_field((string) $payload['query']) : '';
		$country = isset($payload['country']) ? sanitize_key((string) $payload['country']) : 'ua';
		$lang = isset($payload['lang']) ? sanitize_key((string) $payload['lang']) : 'uk';

		if ($query === '') {
			return false;
		}

		try {
			$client = new \SEOJusAI\SERP\SerpClient();
			$items = $client->search($query, $country, $lang);

			$top = [];
			if (is_array($items)) {
				foreach (array_slice($items, 0, 10) as $item) {
					if (!is_array($item)) {
						continue;
					}
					$top[] = [
						'title' => isset($item['title']) ? (string) $item['title'] : '',
						'url' => isset($item['link']) ? (string) $item['link'] : '',
						'snippet' => isset($item['snippet']) ? (string) $item['snippet'] : '',
					];
				}
			}

			$data = [
				'query' => $query,
				'country' => $country,
				'lang' => $lang,
				'top' => $top,
				'raw' => $items,
				'generated_at' => time(),
			];

			$repo = new \SEOJusAI\Snapshots\SnapshotRepository();
			$repo->insert('serp', 1, $data);

			return true;
		} catch (\Throwable $e) {
			\SEOJusAI\Utils\Logger::error('serp_snapshot_task_failed', ['error' => $e->getMessage()]);
			return false;
		}
	}


	private static function run_market_refresh(array $payload): bool {
		$max_q = (int) ($payload['max_queries'] ?? 8);
		$max_q = max(1, min(25, $max_q));
		try {
			$repo = new \SEOJusAI\Competitive\CompetitiveRepository();
			$ref  = new \SEOJusAI\Competitive\MarketRefresher($repo);
			$ref->refresh($max_q, 10, 5);
			return true;
		} catch (\Throwable $e) {
			if (class_exists(\SEOJusAI\Utils\Logger::class)) {
				\SEOJusAI\Utils\Logger::error('market_refresh_failed', ['error' => $e->getMessage(), 'payload' => $payload]);
			}
			return false;
		}
	}

}
