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
use App\Rag\RetrieverInterface;
use Illuminate\Support\Facades\Http;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Laravel\Ai\Tools\ToolNameResolver;
use Mockery;
use Tests\TestCase;

/**
 * Bug #2: intent `definition` sebelumnya tidak punya tool BPS -> fallback .md
 * (citation verified=false). Setelah fix, definition harus pakai tool BPS dan
 * citation verified=true.
 */
class DefinitionBpsToolPathTest extends TestCase
{
    public function test_definition_intent_uses_bps_agent_not_demo_fallback(): void
    {
        config(['bps.enabled' => true, 'bps.key' => 'test-key']);
        // Stub domain list agar tool katalog bisa resolve tanpa hit network nyata.
        Http::fake(['webapi.bps.go.id/*' => Http::response([
            'status' => 'OK', 'data-availability' => 'available',
            'data' => [['pages' => 1, 'total' => 1], [['domain_id' => '0000', 'domain_name' => 'Indonesia', 'domain_url' => 'https://www.bps.go.id']]],
        ])]);

        $provider = Mockery::mock(AiProviderInterface::class);
        $provider->shouldReceive('chatWithTools')->once()->andReturnUsing(function (object $input, iterable $tools): ChatProviderOutput {
            // Pastikan minimal satu tool disediakan untuk definition.
            $this->assertNotEmpty(iterator_to_array($tools, false), 'definition intent harus punya tool BPS');

            // Simulasikan model memanggil tool katalog agar citation terkumpul.
            $varsTool = collect($tools)->first(
                fn (Tool $tool) => ToolNameResolver::resolve($tool) === 'ListIndicatorsTool',
            );
            if ($varsTool instanceof Tool) {
                $varsTool->handle(new Request(['domain' => '0000']));
            }

            return new ChatProviderOutput('{"status":"answered","answer":"Inflasi adalah ... (BPS)","citationSourceIds":["0000"]}');
        });
        $provider->shouldNotReceive('chat');

        $retriever = Mockery::mock(RetrieverInterface::class);
        $retriever->shouldNotReceive('retrieve'); // tidak boleh fallback demo

        $service = $this->service($provider, $retriever);
        $response = $service->handle('Apa itu inflasi?');

        // Inti fix: definition kini lewat BPS agent (chatWithTools terpanggil),
        // bukan fallback .md demo. Status answered, bukan no_evidence.
        $this->assertSame('answered', $response->status);
        $this->assertStringContainsString('BPS', (string) $response->answer);
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
