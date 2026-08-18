<?php

namespace App\Providers;

use App\Ai\AiProviderInterface;
use App\Ai\PromptBuilder;
use App\Bps\BpsAgent;
use App\Bps\BpsApiClient;
use App\Bps\BpsToolRegistry;
use App\Rag\DemoLexicalRetriever;
use App\Rag\KnowledgeLoader;
use App\Rag\RetrieverInterface;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Support\ServiceProvider;

class RagServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(KnowledgeLoader::class, function ($app) {
            return new KnowledgeLoader(base_path('data/knowledge'));
        });

        // Muat docs sekali (singleton) — knowledge demo statis.
        $this->app->singleton(RetrieverInterface::class, function ($app) {
            $docs = $app->make(KnowledgeLoader::class)->load();

            return new DemoLexicalRetriever($docs);
        });

        // Satu-satunya HTTP client ke webapi.bps.go.id (key via path/query, cache 24h).
        $this->app->singleton(BpsApiClient::class, function ($app) {
            return new BpsApiClient(
                baseUrl: (string) config('bps.base_url'),
                key: (string) config('bps.key'),
                timeoutSecs: (int) config('bps.http.timeout_sec', 15),
                cacheTtlHours: (int) config('bps.cache.ttl_hours', 24),
                cachePrefix: (string) config('bps.cache.prefix', 'bps:'),
                cacheEnabled: (bool) config('bps.cache.enabled', true),
                cache: $app->make(Repository::class),
            );
        });

        $this->app->singleton(BpsToolRegistry::class, fn ($app) => new BpsToolRegistry(
            $app->make(BpsApiClient::class),
        ));

        $this->app->scoped(BpsAgent::class, fn ($app) => new BpsAgent(
            provider: $app->make(AiProviderInterface::class),
            registry: $app->make(BpsToolRegistry::class),
            promptBuilder: $app->make(PromptBuilder::class),
            maxToolCalls: (int) config('bps.agent.max_tool_calls', 4),
            timeoutSec: (int) config('bps.agent.timeout_sec', 60),
        ));
    }
}
