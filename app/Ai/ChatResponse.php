<?php

namespace App\Ai;

use App\Rag\Citation;
use JsonSerializable;

/**
 * Normalized response ke client (per DOCS/03_API/02_INTERNAL_API_CONTRACT.md).
 * Tidak pernah expose schema provider.
 */
final class ChatResponse implements JsonSerializable
{
    public function __construct(
        public readonly string $requestId,
        public readonly string $status, // answered|clarification_required|no_evidence|out_of_scope|rate_limited|provider_error
        public readonly ?string $answer = null,
        public readonly ?string $clarificationQuestion = null,
        public readonly array $citations = [],
    ) {}

    public function jsonSerialize(): array
    {
        $payload = [
            'requestId' => $this->requestId,
            'status' => $this->status,
        ];
        if ($this->answer !== null) {
            $payload['answer'] = $this->answer;
        }
        if ($this->clarificationQuestion !== null) {
            $payload['clarificationQuestion'] = $this->clarificationQuestion;
        }
        if ($this->citations !== []) {
            $payload['citations'] = array_map(fn (Citation $c) => [
                'sourceId' => $c->sourceId,
                'title' => $c->title,
                'url' => $c->url,
                'snippet' => $c->snippet,
                'verified' => $c->verified,
            ], $this->citations);
        }

        return $payload;
    }
}
