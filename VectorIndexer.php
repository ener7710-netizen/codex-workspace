<?php
declare(strict_types=1);

namespace SEOJusAI\Vectors;

defined('ABSPATH') || exit;

final class VectorIndexer {

    private EmbeddingProviderInterface $provider;
    private VectorStore $store;

    public function __construct(EmbeddingProviderInterface $provider, ?VectorStore $store = null) {
        $this->provider = $provider;
        $this->store = $store ?? new VectorStore();
    }

    public function index_post(int $post_id): array {
        $post = get_post($post_id);
        if (!$post || $post->post_status !== 'publish') {
            return ['ok'=>false,'message'=>__('Запис не знайдено або не опубліковано.', 'seojusai')];
        }
        if (!$this->provider->is_ready()) {
            return ['ok'=>false,'message'=>__('Немає ключа OpenAI для embeddings.', 'seojusai')];
        }

        $chunks = $this->chunk_post($post);
        $count = 0;

        foreach ($chunks as $chunk) {
            $vec = $this->provider->embed($chunk['text']);
            if (!$vec) continue;
            if ($this->store->upsert('post', (int)$post_id, $chunk['hash'], $chunk['text'], $vec, $this->provider->model(), $this->provider->dims())) {
                $count++;
            }
        }

        return ['ok'=>true,'indexed'=>$count,'chunks'=>count($chunks)];
    }

    /** @return array<int,array{hash:string,text:string}> */
    private function chunk_post(\WP_Post $post): array {
        $text = (string)$post->post_title . "\n" . wp_strip_all_tags((string)$post->post_content);
        $text = preg_replace('/\s+/', ' ', $text) ?? $text;

        $max = 1200; // chars
        $chunks = [];
        $i=0;
        $len = strlen($text);

        while ($i < $len) {
            $piece = trim(substr($text, $i, $max));
            if ($piece === '') break;
            $chunks[] = [
                'hash' => md5($post->ID . ':' . $i . ':' . $piece),
                'text' => $piece,
            ];
            $i += $max;
        }
        return $chunks;
    }
}
