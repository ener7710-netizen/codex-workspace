<?php
declare(strict_types=1);

namespace SEOJusAI\Modules;

use SEOJusAI\Core\Contracts\ModuleInterface;
use SEOJusAI\Core\Kernel;
use SEOJusAI\AI\OpenAIKey;
use SEOJusAI\AI\Providers\OpenAIClient;
use SEOJusAI\Vectors\OpenAIEmbeddingProvider;
use SEOJusAI\Vectors\VectorIndexer;
use SEOJusAI\Vectors\VectorStore;
use SEOJusAI\Vectors\VectorHooks;

defined('ABSPATH') || exit;

final class VectorMemoryModule implements ModuleInterface {

    public function get_slug(): string { return 'vectors'; }

    public function register(Kernel $kernel): void {
        $kernel->register_module($this->get_slug(), $this);
    }

    public function init(Kernel $kernel): void {

        // Indexing hooks (publish/update/delete) -> enqueue vector tasks.
        if (class_exists(VectorHooks::class)) {
            VectorHooks::register();
        }

        add_action('rest_api_init', function (): void {

            register_rest_route('seojusai/v1', '/vectors/index', [
                'methods'  => 'POST',
                'permission_callback' => ['SEOJusAI\\Rest\\RestKernel', 'can_manage'],
                'callback' => function (\WP_REST_Request $req) {
                    $post_id = (int) $req->get_param('post_id');

                    $key = OpenAIKey::get();
                    $client = new OpenAIClient($key, 'gpt-4.1');
                    $provider = new OpenAIEmbeddingProvider($client);
                    $indexer = new VectorIndexer($provider);

                    return rest_ensure_response($indexer->index_post($post_id));
                },
            ]);

            register_rest_route('seojusai/v1', '/vectors/search', [
                'methods'  => 'POST',
                'permission_callback' => ['SEOJusAI\\Rest\\RestKernel', 'can_manage'],
                'callback' => function (\WP_REST_Request $req) {
                    $query = (string) $req->get_param('q');

                    $key = OpenAIKey::get();
                    $client = new OpenAIClient($key, 'gpt-4.1');
                    $provider = new OpenAIEmbeddingProvider($client);

                    if (!$provider->is_ready()) {
                        return rest_ensure_response(['ok'=>false,'message'=>__('Немає ключа OpenAI для embeddings.', 'seojusai')]);
                    }

                    $vec = $provider->embed($query);
                    if (!$vec) {
                        return rest_ensure_response(['ok'=>false,'message'=>__('Не вдалося створити embedding.', 'seojusai')]);
                    }

                    $store = new VectorStore();
                    $hits = $store->search($vec, 8, $req->get_param('type') ? (string)$req->get_param('type') : null);

                    return rest_ensure_response(['ok'=>true,'hits'=>$hits]);
                },
            ]);

        }, 20);
    }
}
