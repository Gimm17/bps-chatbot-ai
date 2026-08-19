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
use Laravel\Ai\Messages\Message;
use Laravel\Ai\Messages\MessageRole;
use Laravel\Ai\Tools\Request;
use Laravel\Ai\Tools\ToolNameResolver;
use Mockery;
use Tests\TestCase;

/**
 * Bug (live, 19 Aug 2026): Q2 "JUMLAH DATA PENDUDUK SULAWESI TENGAH TAHUN 2026"
 * dalam sesi yang sama dengan Q1 "APA ITU INFLASI" jawabannya diawali definisi
 * inflasi dari Q1 (context bleed) lalu gagal mengambil data. Root cause:
 * BpsAgent menyuntikkan history sebagai N user-message berurutan; OpenAI
 * tool-loop menganggapnya konteks sekarang, model melengkapi jawaban Q1
 * sambil menjawab Q2. Fix: agent menerima SATU user message berisi pertanyaan
 * efektif (gabungan follow-up hanya untuk numeric clarification).
 */
class ContextBleedRegressionTest extends TestCase
{
    public function test_agent_receives_single_user_message_not_history_turns(): void
    {
        config(['bps.enabled' => true, 'bps.key' => 'test-key']);
        Http::fake(['webapi.bps.go.id/*' => Http::response([
            'status' => 'OK', 'data-availability' => 'available',
            'data' => [['pages' => 1, 'total' => 1], [['domain_id' => '7200', 'domain_name' => 'Sulawesi Tengah', 'domain_url' => 'https://sulteng.bps.go.id']]],
        ])]);

        $captured = null;
        $provider = Mockery::mock(AiProviderInterface::class);
        $provider->shouldReceive('chatWithTools')->once()->andReturnUsing(function ($input, iterable $tools) use (&$captured) {
            $captured = $input;
            $domainTool = collect($tools)->first(fn (Tool $t) => ToolNameResolver::resolve($t) === 'ListDomainsTool');
            $domainTool?->handle(new Request(['type' => 'prov']));

            return new ChatProviderOutput('{"status":"answered","answer":"Jumlah penduduk 2026 ...","citationSourceIds":["7200"]}');
        });
        $provider->shouldNotReceive('chat');
        $retriever = Mockery::mock(RetrieverInterface::class);
        $retriever->shouldNotReceive('retrieve');

        $promptBuilder = new PromptBuilder;
        $agent = new BpsAgent(
            provider: $provider,
            registry: new BpsToolRegistry($this->app->make(BpsApiClient::class)),
            promptBuilder: $promptBuilder,
            maxToolCalls: 4,
            timeoutSec: 60,
        );

        // history = pertanyaan inflasi sebelumnya; pertanyaan sekarang = numeric.
        $agent->run(
            'berapa jumlah penduduk Sulawesi Tengah tahun 2026?',
            'numeric_statistic',
            ['apa itu inflasi?'],
        );

        $this->assertNotNull($captured, 'chatWithTools harus dipanggil');
        $userMessages = array_values(array_filter(
            $captured->messages,
            fn (Message $m) => $m->role === MessageRole::User,
        ));
        $this->assertCount(1, $userMessages, 'Agent harus menerima SATU user message, bukan history sebagai user turns');
        $text = (string) $userMessages[0]->content;
        $this->assertStringContainsString('penduduk', $text);
        $this->assertStringNotContainsString('inflasi', $text, 'Pertanyaan efektif tidak boleh membawa topik inflasi dari turn sebelumnya');
    }

    public function test_followup_period_question_merges_with_prior_numeric_question(): void
    {
        // Follow-up "tahun 2023" setelah "berapa jumlah penduduk Sulawesi Tengah?"
        // harus digabung menjadi satu pertanyaan efektif lengkap (agent menerima
        // konteks tanpa membawa jawaban/topik lain).
        $guard = new ScopeGuard(useLlmLayer: false);
        $decision = $guard->classifyWithHistory('tahun 2023', ['berapa jumlah penduduk Sulawesi Tengah?']);
        $this->assertSame('numeric_statistic', $decision->intent);
        $this->assertSame([], $decision->missing);
    }
}
