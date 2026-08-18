<?php

namespace Tests\Unit\Bps;

use App\Ai\AiProviderInterface;
use App\Ai\ChatProviderInput;
use App\Ai\ChatProviderOutput;
use App\Ai\ChatResult;
use App\Ai\PromptBuilder;
use App\Bps\BpsAgent;
use App\Bps\BpsCitation;
use App\Bps\BpsToolRegistry;
use Illuminate\Support\Facades\Http;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Laravel\Ai\Tools\ToolNameResolver;
use Mockery;
use Tests\TestCase;

class BpsAgentTest extends TestCase
{
    public function test_run_executes_registry_tool_and_collects_only_backend_citations(): void
    {
        Http::fake(['webapi.bps.go.id/*' => Http::response([
            'status' => 'OK', 'data-availability' => 'available',
            'data' => [['pages' => 1, 'total' => 1], [[
                'domain_id' => '3200',
                'domain_name' => 'Jawa Barat',
                'domain_url' => 'https://jabar.bps.go.id',
            ]]],
        ])]);
        $provider = Mockery::mock(AiProviderInterface::class);
        $provider->shouldReceive('chatWithTools')
            ->once()
            ->andReturnUsing(function (ChatProviderInput $input, iterable $tools, int $cap): ChatProviderOutput {
                $this->assertSame(4, $cap);
                $this->assertStringContainsString('BPS AI Assistant', $input->instructions);

                $domainTool = collect($tools)->first(
                    fn (Tool $tool) => ToolNameResolver::resolve($tool) === 'ListDomainsTool',
                );
                $result = json_decode($domainTool->handle(new Request(['type' => 'prov'])), true);

                $this->assertSame('3200', $result['_citations'][0]['sourceId']);

                return new ChatProviderOutput(
                    text: '{"status":"answered","answer":"Data Jawa Barat","citationSourceIds":["3200","invented"]}',
                );
            });
        $agent = new BpsAgent(
            provider: $provider,
            registry: $this->app->make(BpsToolRegistry::class),
            promptBuilder: $this->app->make(PromptBuilder::class),
            maxToolCalls: 4,
            timeoutSec: 60,
        );

        $result = $agent->run('data Jawa Barat', 'numeric_statistic');
        $sources = $agent->collectedSources();

        $this->assertInstanceOf(ChatResult::class, $result);
        $this->assertSame(['3200', 'invented'], $result->citationSourceIds);
        $this->assertArrayHasKey('3200', $sources);
        $this->assertArrayNotHasKey('invented', $sources);
        $this->assertInstanceOf(BpsCitation::class, $sources['3200']);
        $this->assertSame('https://jabar.bps.go.id', $sources['3200']->url);
    }

    public function test_run_returns_null_for_intent_without_tools(): void
    {
        $provider = Mockery::mock(AiProviderInterface::class);
        $provider->shouldNotReceive('chatWithTools');
        $agent = new BpsAgent(
            provider: $provider,
            registry: $this->app->make(BpsToolRegistry::class),
            promptBuilder: $this->app->make(PromptBuilder::class),
            maxToolCalls: 4,
            timeoutSec: 60,
        );

        $this->assertNull($agent->run('cara daftar layanan', 'bps_service'));
    }

    public function test_each_run_clears_citations_before_no_tool_fallback(): void
    {
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

            return new ChatProviderOutput('{"status":"answered","answer":"ok","citationSourceIds":["3200"]}');
        });
        $agent = new BpsAgent(
            provider: $provider,
            registry: $this->app->make(BpsToolRegistry::class),
            promptBuilder: $this->app->make(PromptBuilder::class),
            maxToolCalls: 4,
            timeoutSec: 60,
        );

        $agent->run('data Jawa Barat', 'numeric_statistic');
        $this->assertArrayHasKey('3200', $agent->collectedSources());

        $this->assertNull($agent->run('cara layanan', 'bps_service'));
        $this->assertSame([], $agent->collectedSources());
    }

    public function test_provider_failure_returns_no_evidence_without_leaking_error(): void
    {
        $provider = Mockery::mock(AiProviderInterface::class);
        $provider->shouldReceive('chatWithTools')->once()->andThrow(new \RuntimeException('secret upstream detail'));
        $agent = new BpsAgent(
            provider: $provider,
            registry: $this->app->make(BpsToolRegistry::class),
            promptBuilder: $this->app->make(PromptBuilder::class),
            maxToolCalls: 4,
            timeoutSec: 60,
        );

        $result = $agent->run('data BPS', 'numeric_statistic');

        $this->assertSame('no_evidence', $result->status);
        $this->assertNull($result->answer);
        $this->assertSame([], $agent->collectedSources());
    }
}
