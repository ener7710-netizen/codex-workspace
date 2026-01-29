<?php
declare(strict_types=1);

namespace SEOJusAI\SERP;

defined('ABSPATH') || exit;

/**
 * SerpClient
 *
 * Безпечний клієнт SERP.
 * За замовчуванням НЕ скрейпить Google напряму.
 * Використовує фільтр/ендпойнт (можна підʼєднати Gemini-шар або власний сервіс).
 */
final class SerpClient {

	/**
	 * Повертає масив елементів:
	 * [
	 *   ['title' => string, 'url' => string, 'snippet' => string],
	 *   ...
	 * ]
	 *
	 * @return array<int, array{title?:string,url?:string,snippet?:string}>
	 */
	public function search(string $query, int $limit = 10): array {

		$query = trim($query);
		$limit = max(1, min(20, $limit));

		if ($query === '') {
			return [];
		}

		/**
		 * 1) Дозволяємо будь-якому модулю повернути SERP без мережі:
		 * add_filter('seojusai/serp/search', fn($items,$query,$limit)=>..., 10, 3);
		 */
		$items = apply_filters('seojusai/serp/search', null, $query, $limit);
		if (is_array($items)) {
			return array_slice($this->normalize($items), 0, $limit);
		}

		/**
		 * 2) Опційний HTTP endpoint (власний сервіс або Gemini-layer).
		 * Зберігається як option: seojusai_serp_endpoint
		 * Формат POST JSON: {query, limit}
		 */
		$endpoint = (string) get_option('seojusai_serp_endpoint', '');
		if ($endpoint === '') {
			return [];
		}

		$args = [
			'timeout' => 15,
			'headers' => ['Content-Type' => 'application/json; charset=utf-8'],
			'body'    => wp_json_encode(['query' => $query, 'limit' => $limit], JSON_UNESCAPED_UNICODE),
		];

		$res = wp_remote_post($endpoint, $args);
		if (is_wp_error($res)) {
			return [];
		}

		$code = (int) wp_remote_retrieve_response_code($res);
		$body = (string) wp_remote_retrieve_body($res);

		if ($code < 200 || $code >= 300 || $body === '') {
			return [];
		}

		$data = json_decode($body, true);
		if (!is_array($data)) {
			return [];
		}

		return array_slice($this->normalize($data), 0, $limit);
	}

	/**
	 * @param mixed $data
	 * @return array<int, array{title?:string,url?:string,snippet?:string}>
	 */
	private function normalize($data): array {

		$items = [];

		if (isset($data['items']) && is_array($data['items'])) {
			$data = $data['items'];
		}

		if (!is_array($data)) {
			return [];
		}

		foreach ($data as $row) {
			if (!is_array($row)) {
				continue;
			}
			$url = isset($row['url']) ? (string) $row['url'] : '';
			if ($url === '') {
				continue;
			}
			$items[] = [
				'title'   => isset($row['title']) ? (string) $row['title'] : '',
				'url'     => $url,
				'snippet' => isset($row['snippet']) ? (string) $row['snippet'] : '',
			];
		}

		return $items;
	}
}
