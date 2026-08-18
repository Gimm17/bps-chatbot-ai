<?php

namespace App\Ai;

use Laravel\Ai\Contracts\Tool;

/**
 * Boundary provider — core app tidak pernah bicara langsung ke LimitRouter.
 *
 *     AiProviderInterface
 *         └── LimitRouterProvider   (via Laravel\Ai openai-compatible driver)
 *         └── FutureLocalProvider  (open-weight model)
 */
interface AiProviderInterface
{
    public function chat(ChatProviderInput $input): ChatProviderOutput;

    /**
     * Tool-use chat dengan batas jumlah eksekusi tool.
     *
     * @param  iterable<Tool>  $tools
     */
    public function chatWithTools(ChatProviderInput $input, iterable $tools, int $maxToolCalls = 4): ChatProviderOutput;

    /**
     * @return list<array{id:string,label:string}>
     */
    public function listModels(): array;
}
