<?php

namespace Tests\Unit\Ai;

use App\Ai\AiProviderInterface;
use App\Ai\ChatProviderOutput;
use App\Ai\ChatService;
use App\Ai\PromptBuilder;
use App\Ai\ScopeGuard;
use App\Bps\BpsAgent;
use App\Bps\BpsApiClient;
use App\Bps\BpsToolRegistry;
use App\Rag\RetrievedSource;
use App\Rag\RetrieverInterface;
use Illuminate\Support\Facades\Http;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Laravel\Ai\Tools\ToolNameResolver;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ChatServiceBranchTest extends TestCase
{
    public function test_enabled_bps_branch_maps_only_backend_verified_citations(): void
    {
        config(['bps.enabled' => true, 'bps.key' => 'test-key']);
        Http::fake(['webapi.bps.go.id/*' => Http::response([
            'status' => 'OK', 'data-availability' => 'available',
            'data' => [['pages' => 1, 'total' => 1], [[
                'domain_id' => '3200', 'domain_name' => 'Jawa Barat', 'domain_url' => 'https://jabar.bps.go.id',
            ]]],
        ])]);
        $provider = Mockery::mock(AiProviderInterface::class);
        $provider->shouldReceive('chatWithTools')->once()->andReturnUsing(function ($input, iterable $tools): ChatProviderOutput {
            $domainTool = collect($tools)->first(
                fn (Tool $tool) => ToolNameResolver::resolve($tool) === 'ListDomainsTool',
            );
            $domainTool->handle(new Request(['type' => 'prov']));

            return new ChatProviderOutput('{"status":"answered","answer":"Jawa Barat","citationSourceIds":["3200","invented"]}');
        });
        $provider->shouldNotReceive('chat');
        $retriever = Mockery::mock(RetrieverInterface::class);
        $retriever->shouldNotReceive('retrieve');
        $service = $this->service($provider, $retriever);

        $response = $service->handle('Berapa jumlah penduduk Provinsi Jawa Barat tahun 2025?');

        $this->assertSame('answered', $response->status);
        $this->assertCount(1, $response->citations);
        $this->assertSame('3200', $response->citations[0]->sourceId);
        $this->assertTrue($response->citations[0]->verified);
    }

    #[DataProvider('disabledBpsProvider')]
    public function test_disabled_bps_falls_back_to_legacy_retrieval_and_chat(bool $enabled, string $key): void
    {
        config(['bps.enabled' => $enabled, 'bps.key' => $key]);
        $provider = Mockery::mock(AiProviderInterface::class);
        $provider->shouldNotReceive('chatWithTools');
        $provider->shouldReceive('chat')->once()->andReturn(new ChatProviderOutput(
            '{"status":"answered","answer":"Definisi demo","citationSourceIds":["SRC-DEMO-001"]}',
        ));
        $retriever = Mockery::mock(RetrieverInterface::class);
        $retriever->shouldReceive('retrieve')->once()->andReturn([$this->demoSource()]);
        $service = $this->service($provider, $retriever);

        $response = $service->handle('Apa itu inflasi?');

        $this->assertSame('answered', $response->status);
        $this->assertCount(1, $response->citations);
        $this->assertFalse($response->citations[0]->verified);
    }

    public static function disabledBpsProvider(): array
    {
        return [
            'feature disabled' => [false, 'test-key'],
            'key empty' => [true, ''],
        ];
    }

    public function test_intent_without_bps_tools_falls_back_even_when_enabled(): void
    {
        config(['bps.enabled' => true, 'bps.key' => 'test-key']);
        $provider = Mockery::mock(AiProviderInterface::class);
        $provider->shouldNotReceive('chatWithTools');
        $provider->shouldReceive('chat')->once()->andReturn(new ChatProviderOutput(
            '{"status":"answered","answer":"Layanan demo","citationSourceIds":["SRC-DEMO-001"]}',
        ));
        $retriever = Mockery::mock(RetrieverInterface::class);
        $retriever->shouldReceive('retrieve')->once()->andReturn([$this->demoSource()]);
        $service = $this->service($provider, $retriever);

        $response = $service->handle('Bagaimana cara mengakses layanan BPS?');

        $this->assertSame('answered', $response->status);
        $this->assertSame('Layanan demo', $response->answer);
    }

    public function test_container_resolves_request_scoped_chat_services_and_agents(): void
    {
        $firstService = $this->app->make(ChatService::class);
        $firstAgent = $this->app->make(BpsAgent::class);

        $this->app->forgetScopedInstances();

        $this->assertNotSame($firstService, $this->app->make(ChatService::class));
        $this->assertNotSame($firstAgent, $this->app->make(BpsAgent::class));
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

    private function demoSource(): RetrievedSource
    {
        return new RetrievedSource(
            sourceId: 'SRC-DEMO-001',
            title: 'Demo BPS',
            content: 'DEMO_NOT_VERIFIED',
            sourceUrl: null,
            score: 1.0,
        );
    }
}
