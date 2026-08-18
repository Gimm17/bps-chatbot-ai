<?php

namespace Tests\Unit\Ai;

use App\Ai\ChatResponse;
use App\Rag\Citation;
use PHPUnit\Framework\TestCase;

class ChatResponseTest extends TestCase
{
    public function test_serializes_verified_citation_flag_for_clients(): void
    {
        $response = new ChatResponse(
            requestId: 'req-1',
            status: 'answered',
            citations: [new Citation('3200', 'Jawa Barat', 'https://jabar.bps.go.id', null, true)],
        );

        $payload = $response->jsonSerialize();

        $this->assertTrue($payload['citations'][0]['verified']);
    }
}
