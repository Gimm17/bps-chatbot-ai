<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class BpsCommandsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        config(['bps.key' => 'test-key-123']);
    }

    public function test_preload_warms_domains_indicators_and_variables(): void
    {
        Http::fake(['webapi.bps.go.id/*' => Http::response([
            'status' => 'OK',
            'data-availability' => 'available',
            'data' => [['pages' => 1, 'total' => 1], [['id' => 1]]],
        ])]);

        $this->artisan('bps:preload')
            ->expectsOutputToContain('Preload complete')
            ->assertSuccessful();

        Http::assertSentCount(5);
        foreach ([
            '/domain/model/domain/type/all',
            '/list/model/indicators/domain/0000',
            '/list/model/var/domain/0000',
            '/list/model/indicators/domain/3200',
            '/list/model/var/domain/3200',
        ] as $path) {
            Http::assertSent(fn ($request) => str_contains($request->url(), $path));
        }
    }

    public function test_preload_fails_without_key_and_does_not_send_http(): void
    {
        config(['bps.key' => '']);
        Http::fake();

        $this->artisan('bps:preload')
            ->expectsOutputToContain('BPS_WEBAPI_KEY')
            ->assertFailed();

        Http::assertNothingSent();
    }

    public function test_preload_fails_when_upstream_response_is_not_ok(): void
    {
        Http::fake(['webapi.bps.go.id/*' => Http::response([
            'status' => 'Error', 'message' => 'Invalid parameter',
        ])]);

        $this->artisan('bps:preload')
            ->expectsOutputToContain('Invalid parameter')
            ->assertFailed();
    }

    public function test_clear_cache_command_runs(): void
    {
        Cache::put('bps:v2:test', 'x', 60);

        $this->artisan('bps:clear-cache')
            ->expectsOutputToContain('BPS dedicated cache store cleared')
            ->assertSuccessful();

        $this->assertNull(Cache::get('bps:v2:test'));
    }
}
