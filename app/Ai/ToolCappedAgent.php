<?php

namespace App\Ai;

use Laravel\Ai\AnonymousAgent;

/**
 * Anonymous Laravel AI agent dengan step budget eksplisit.
 * Satu extra step disediakan agar model dapat menjawab setelah tool terakhir.
 */
final class ToolCappedAgent extends AnonymousAgent
{
    public function __construct(
        string $instructions,
        iterable $messages,
        iterable $tools,
        private readonly int $stepLimit,
    ) {
        parent::__construct($instructions, $messages, $tools);
    }

    public function maxSteps(): int
    {
        return max(1, $this->stepLimit);
    }
}
