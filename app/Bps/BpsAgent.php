<?php

namespace App\Bps;

use App\Ai\AiProviderInterface;
use App\Ai\ChatProviderInput;
use App\Ai\ChatResult;
use App\Ai\PromptBuilder;
use Laravel\Ai\Messages\Message;
use Laravel\Ai\Messages\MessageRole;

/** Orkestrasi native Laravel AI tools BPS berdasarkan intent ScopeGuard. */
final class BpsAgent
{
    /** @var array<string, BpsCitation> */
    private array $collectedSources = [];

    public function __construct(
        private readonly AiProviderInterface $provider,
        private readonly BpsToolRegistry $registry,
        private readonly PromptBuilder $promptBuilder,
        private readonly int $maxToolCalls,
        private readonly int $timeoutSec,
    ) {}

    public function run(string $question, string $intent): ?ChatResult
    {
        // Web SAPIs commonly default to 30s; allow every bounded model step plus synthesis.
        @set_time_limit(self::executionTimeLimit($this->maxToolCalls, $this->timeoutSec));

        $this->collectedSources = [];

        $tools = $this->registry->forIntent($intent);
        if ($tools === []) {
            return null;
        }
        $collect = function (BpsCitation $citation): void {
            $this->collectedSources[$citation->sourceId] = $citation;
        };
        $tools = array_map(
            fn ($tool) => new CitationCollectingTool($tool, $collect),
            $tools,
        );
        $input = new ChatProviderInput(
            messages: [new Message(MessageRole::User, $question)],
            instructions: $this->promptBuilder->buildInstructions($question, []),
            timeout: $this->timeoutSec,
        );

        try {
            $output = $this->provider->chatWithTools($input, $tools, $this->maxToolCalls);
        } catch (\Throwable $e) {
            logger()->warning('BPS AI agent failed.', ['exception' => $e::class]);

            return new ChatResult('no_evidence', null, null, []);
        }

        return ChatResult::parse($output->text);
    }

    public static function executionTimeLimit(int $maxToolCalls, int $timeoutSec): int
    {
        return (max(0, $maxToolCalls) + 2) * max(1, $timeoutSec) + 5;
    }

    /** @return array<string, BpsCitation> */
    public function collectedSources(): array
    {
        return $this->collectedSources;
    }
}
