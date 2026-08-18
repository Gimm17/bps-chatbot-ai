<?php

namespace App\Ai;

use Laravel\Ai\Messages\Message;

/**
 * Input ke AI provider (internal abstraction, bukan schema LimitRouter).
 */
final class ChatProviderInput
{
    /**
     * @param  list<Message>  $messages
     */
    public function __construct(
        public readonly array $messages,
        public readonly ?string $instructions = null,
        public readonly ?string $model = null,
        public readonly ?int $timeout = null,
    ) {}
}
