<?php

namespace App\Ai;

/**
 * Output dari AI provider (normalized, bukan schema LimitRouter).
 */
final class ChatProviderOutput
{
    public function __construct(
        public readonly string $text,
        public readonly ?string $model = null,
        public readonly ?array $usage = null,
    ) {}
}
