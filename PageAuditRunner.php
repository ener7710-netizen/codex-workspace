<?php
declare(strict_types=1);

namespace SEOJusAI\Analyze;

use SEOJusAI\ContentScore\ScoreCalculator;
use SEOJusAI\Proposals\ProposalBuilder;
use SEOJusAI\Snapshots\SnapshotRepository;

defined('ABSPATH') || exit;

final class PageAuditRunner {

	public static function run(int $post_id): void {

		$post = get_post($post_id);
		if (!$post) {
			return;
		}

		$analysis = [
			'post_id' => $post_id,
			'url' => (string) get_permalink($post_id),
			'timestamp' => time(),
		];

		// 🔎 Page facts (контент/структура)
		if (class_exists(PageFactsProvider::class)) {
			$analysis['page'] = (new PageFactsProvider())->build($post_id);
		}

		// 🔗 Перелінковка
		if (class_exists(LinkingLogicFactsProvider::class)) {
			$analysis['linking'] = (new LinkingLogicFactsProvider())->build($post_id);
		}

		// 🧩 Schema facts
		if (class_exists(SchemaFactsProvider::class)) {
			$analysis['schema'] = (new SchemaFactsProvider())->build($post_id);
		}

		// 🏛️ Local SEO
		if (class_exists(LocalSEOFactsProvider::class)) {
			$analysis['local'] = (new LocalSEOFactsProvider())->build($post_id);
		}

		// 👥 Social proof
		if (class_exists(SocialProofFactsProvider::class)) {
			$analysis['social_proof'] = (new SocialProofFactsProvider())->build($post_id);
		}

		// ✅ Compliance/YMYL
		if (class_exists(ComplianceFactsProvider::class)) {
			$analysis['compliance'] = (new ComplianceFactsProvider())->build($post_id);
		}

		// 🧠 E‑E‑A‑T
		if (class_exists(EeatFactsProvider::class)) {
			$analysis['eeat'] = (new EeatFactsProvider())->build($post_id);
		}

		// 📊 Контент‑скор (rules-based)
		if (class_exists(ScoreCalculator::class)) {
			$analysis['content_score'] = (new ScoreCalculator())->calculate($post_id);
		}

		// 🧾 Пропозиції оптимізації (детальні items)
		if (class_exists(ProposalBuilder::class)) {
			$analysis['proposals'] = (new ProposalBuilder())->build($post_id);
		}

		// 💾 Зберегти снапшот аудиту (для порівнянь/impact)
		if (class_exists(SnapshotRepository::class)) {
			(new SnapshotRepository())->insert('page_audit', $post_id, $analysis);
		}

		do_action('seojusai/analysis/complete', $analysis);
	}
}
