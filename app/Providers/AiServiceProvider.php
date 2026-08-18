<?php

namespace App\Providers;

use App\Ai\AiProviderInterface;
use App\Ai\ChatService;
use App\Ai\LimitRouterProvider;
use App\Ai\PromptBuilder;
use App\Ai\ScopeGuard;
use App\Rag\RetrieverInterface;
use Illuminate\Support\ServiceProvider;

class AiServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(AiProviderInterface::class, LimitRouterProvider::class);
        $this->app->singleton(ScopeGuard::class, function () {
            return new ScopeGuard(useLlmLayer: true);
        });
        $this->app->singleton(PromptBuilder::class);

        $this->app->singleton(ChatService::class, function ($app) {
            return new ChatService(
                $app->make(AiProviderInterface::class),
                $app->make(RetrieverInterface::class),
                $app->make(ScopeGuard::class),
                $app->make(PromptBuilder::class),
            );
        });
    }
}
