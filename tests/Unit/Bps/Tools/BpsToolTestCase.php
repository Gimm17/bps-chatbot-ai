<?php

namespace Tests\Unit\Bps\Tools;

use App\Bps\BpsApiClient;
use Illuminate\JsonSchema\JsonSchemaTypeFactory;
use Illuminate\Support\Facades\Cache;
use Laravel\Ai\Tools\Request;
use Tests\TestCase;

abstract class BpsToolTestCase extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['bps.key' => 'test-key-123']);
        Cache::flush();
    }

    protected function client(): BpsApiClient
    {
        return $this->app->make(BpsApiClient::class);
    }

    protected function schema(): JsonSchemaTypeFactory
    {
        return new JsonSchemaTypeFactory;
    }

    protected function request(array $arguments = []): Request
    {
        return new Request($arguments);
    }
}
