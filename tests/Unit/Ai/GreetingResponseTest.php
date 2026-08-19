<?php

namespace Tests\Unit\Ai;

use App\Ai\AiProviderInterface;
use App\Ai\ChatService;
use App\Ai\PromptBuilder;
use App\Ai\ScopeGuard;
use App\Bps\BpsAgent;
use App\Bps\BpsToolRegistry;
use App\Bps\BpsApiClient;
use App\Rag\RetrieverInterface;
use Mockery;
use Tests\TestCase;

/**
 * Bug #1: sapaan "halo"/"hai" salah diklasifikasi out_of_scope -> tidak dibalas.
 * Setelah fix: sapaan dibalas ramah (answered), tanpa call provider/retriever.
 */
class GreetingResponseTest extends TestCase
{
    public function test_greeting_replies_answered_without_provider_or_retriever(): void
    {
        $provider = Mockery::mock(AiProviderInterface::class);
        $provider->shouldNotReceive('chat');
        $provider->shouldNotReceive('chatWithTools');

        $retriever = Mockery::mock(RetrieverInterface::class);
        $retriever->shouldNotReceive('retrieve');

        $service = $this->service($provider, $retriever);
        $response = $service->handle('halo');

        $this->assertSame('answered', $response->status);
        $this->assertNotNull($response->answer);
        $this->assertNotEmpty($response->answer, 'Greeting harus dibalas, bukan kosong');
        $this->assertStringNotContainsString('difokuskan', $response->answer, 'Jangan balas greeting dengan pesan out-of-scope');
    }

    private function service(AiProviderInterface $provider, RetrieverInterface $retriever): ChatService
    {
        $promptBuilder = new PromptBuilder;
        $agent = new BpsAgent(
            provider: $provider,
            registry: new BpsToolRegistry($this->app->make(BpsApiClient::class)),
            promptBuilder: $promptBuilder,
            maxToolCalls: 4,
            timeoutSec: 60,
        );

        return new ChatService(
            provider: $provider,
            retriever: $retriever,
            scopeGuard: new ScopeGuard(useLlmLayer: false),
            promptBuilder: $promptBuilder,
            bpsAgent: $agent,
        );
    }
}
