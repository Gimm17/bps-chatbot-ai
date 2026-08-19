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

    /**
     * @param  list<string>  $history  pesan user sebelumnya dalam sesi (untuk
     *   context multi-turn; tidak mengubah cap tool/step agar tidak bloat).
     */
    public function run(string $question, string $intent, array $history = []): ?ChatResult
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

        // Konteks multi-turn: sertakan pesan user sebelumnya agar model mengingat
        // wilayah/periode yang sudah dibahas di bubble sebelumnya. Hanya pesan user
        // (bukan jawaban bot) agar context tetap ringkas dan tidak mengacaukan cap.
        $messages = [];
        foreach (array_slice(array_values($history), -5) as $prev) {
            $messages[] = new Message(MessageRole::User, $prev);
        }
        $messages[] = new Message(MessageRole::User, $question);

        $input = new ChatProviderInput(
            messages: $messages,
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
