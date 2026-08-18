<?php

namespace App\Providers;

use App\Rag\DemoLexicalRetriever;
use App\Rag\KnowledgeLoader;
use App\Rag\RetrieverInterface;
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
    }
}
