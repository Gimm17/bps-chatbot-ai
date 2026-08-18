<?php

namespace App\Ai;

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
     * @return list<array{id:string,label:string}>
     */
    public function listModels(): array;
}
