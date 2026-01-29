<?php
declare(strict_types=1);

namespace SEOJusAI\Explain;

defined('ABSPATH') || exit;

/**
 * ExplanationRepository (Legacy wrapper)
 *
 * У 2026 SEOJusAI використовує ExplainRepository як єдине джерело істини.
 * Цей клас збережено для backward-compat (старі виклики), але він не має
 * власної БД-логіки і не пише "в інші колонки".
 */
final class ExplanationRepository {

	private ExplainRepository $repo;

	public function __construct(?ExplainRepository $repo = null) {
		$this->repo = $repo instanceof ExplainRepository ? $repo : new ExplainRepository();
	}

	/**
	 * @param string $entity_type Напр.: 'site' або 'post'
	 * @param int    $entity_id   Для поста = post_id.
	 * @param string $decision_hash Хеш рішення/пакета.
	 * @param string $explanation Текст пояснення (людський).
	 * @param string $risk Рівень ризику.
	 * @param array<string,mixed> $meta Додаткова структура (evidence, urls, etc).
	 */
	public function save(
		string $entity_type,
		int $entity_id,
		string $decision_hash,
		string $explanation,
		string $risk = 'low',
		array $meta = [],
		?string $model = null,
		?string $prompt = null,
		?string $response = null,
		int $tokens = 0
	): bool {

		$struct = [
			'explanation' => $explanation,
			'meta'        => $meta,
		];

		return $this->repo->save(
			$entity_type,
			$entity_id,
			$decision_hash,
			$struct,
			$risk,
			$entity_type,
			$model,
			$prompt,
			$response,
			$tokens
		);
	}
}
