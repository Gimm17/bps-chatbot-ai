<?php

namespace Tests\Unit\Ai;

use App\Ai\BudgetedTool;
use App\Ai\ChatProviderInput;
use App\Ai\LimitRouterProvider;
use App\Ai\ToolCappedAgent;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Messages\Message;
use Laravel\Ai\Messages\MessageRole;
use Laravel\Ai\Responses\Data\ToolCall;
use Laravel\Ai\Tools\Request;
use Tests\TestCase;

class ChatWithToolsTest extends TestCase
{
    public function test_native_sdk_loop_executes_tool_then_returns_final_text(): void
    {
        ToolCappedAgent::fake([
            new ToolCall('call-1', 'lookup', ['query' => 'inflasi']),
            '{"status":"answered","answer":"ok","citationSourceIds":[]}',
        ])->preventStrayPrompts();
        $tool = new RecordingTool('lookup');

        $output = $this->app->make(LimitRouterProvider::class)->chatWithTools(
            new ChatProviderInput(
                messages: [new Message(MessageRole::User, 'berapa inflasi')],
                instructions: 'Gunakan tool untuk data resmi.',
            ),
            [$tool],
            maxToolCalls: 4,
        );

        $this->assertSame(1, $tool->calls);
        $this->assertSame('inflasi', $tool->lastArguments['query']);
        $this->assertStringContainsString('answered', $output->text);
    }

    public function test_native_sdk_loop_stops_after_configured_tool_call_cap(): void
    {
        ToolCappedAgent::fake([
            new ToolCall('call-1', 'lookup', []),
            new ToolCall('call-2', 'lookup', []),
            '{"status":"no_evidence","answer":null,"citationSourceIds":[]}',
        ])->preventStrayPrompts();
        $tool = new RecordingTool('lookup');

        $output = $this->app->make(LimitRouterProvider::class)->chatWithTools(
            new ChatProviderInput(messages: [new Message(MessageRole::User, 'x')]),
            [$tool],
            maxToolCalls: 2,
        );

        $this->assertSame(2, $tool->calls);
        $this->assertStringContainsString('no_evidence', $output->text);
    }

    public function test_empty_final_tool_step_gets_one_synthesis_call_without_more_tool_execution(): void
    {
        ToolCappedAgent::fake([
            new ToolCall('call-1', 'lookup', []),
            new ToolCall('call-2', 'lookup', []),
            new ToolCall('call-3', 'lookup', []),
            '{"status":"answered","answer":"final","citationSourceIds":[]}',
        ])->preventStrayPrompts();
        $tool = new RecordingTool('lookup');

        $output = $this->app->make(LimitRouterProvider::class)->chatWithTools(
            new ChatProviderInput(messages: [new Message(MessageRole::User, 'x')]),
            [$tool],
            maxToolCalls: 2,
        );

        $this->assertSame(2, $tool->calls);
        $this->assertStringContainsString('answered', $output->text);
    }

    public function test_zero_cap_never_executes_a_tool(): void
    {
        ToolCappedAgent::fake([
            new ToolCall('call-1', 'lookup', []),
            '{"status":"no_evidence","answer":null,"citationSourceIds":[]}',
        ])->preventStrayPrompts();
        $tool = new RecordingTool('lookup');

        $output = $this->app->make(LimitRouterProvider::class)->chatWithTools(
            new ChatProviderInput(messages: [new Message(MessageRole::User, 'x')]),
            [$tool],
            maxToolCalls: 0,
        );

        $this->assertSame(0, $tool->calls);
        $this->assertStringContainsString('no_evidence', $output->text);
    }

    public function test_budget_wrapper_blocks_parallel_calls_over_cap_and_preserves_name(): void
    {
        $delegate = new RecordingTool('lookup');
        $used = 0;
        $consume = static function () use (&$used): bool {
            if ($used >= 2) {
                return false;
            }

            $used++;

            return true;
        };
        $tool = new BudgetedTool($delegate, $consume);

        $first = $tool->handle(new Request);
        $second = $tool->handle(new Request);
        $third = $tool->handle(new Request);

        $this->assertSame('lookup', $tool->name());
        $this->assertSame(2, $delegate->calls);
        $this->assertStringContainsString('tool call limit reached', (string) $third);
        $this->assertStringNotContainsString('error', (string) $first);
        $this->assertStringNotContainsString('error', (string) $second);
    }
}

final class RecordingTool implements Tool
{
    public int $calls = 0;

    public array $lastArguments = [];

    public function __construct(private readonly string $toolName) {}

    public function name(): string
    {
        return $this->toolName;
    }

    public function description(): string
    {
        return 'Record a test lookup.';
    }

    public function handle(Request $request): string
    {
        $this->calls++;
        $this->lastArguments = $request->all();

        return '{"status":"ok"}';
    }

    public function schema(JsonSchema $schema): array
    {
        return ['query' => $schema->string()->description('Query')];
    }
}
