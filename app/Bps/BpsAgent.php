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

    /** @return array<string, BpsCitation> */
    public function collectedSources(): array
    {
        return $this->collectedSources;
    }
}
