<?php

namespace Tests\Unit\Bps;

use App\Bps\BpsApiClient;
use App\Bps\BpsApiException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class BpsApiClientTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['bps.key' => 'test-key-123']);
        Cache::flush();
    }

    public function test_build_url_uses_path_segment_key(): void
    {
        Http::fake(['webapi.bps.go.id/*' => Http::response([
            'status' => 'OK', 'data-availability' => 'available',
            'data' => [['page' => 1, 'pages' => 1, 'total' => 0], []],
        ], 200)]);

        $this->app->make(BpsApiClient::class)->get('/domain/model/domain', ['type' => 'all']);

        Http::assertSent(fn ($r) => str_ends_with($r->url(), '/key/test-key-123')
            && str_contains($r->url(), '/type/all'));
    }

    public function test_cache_roundtrip_preserves_top_level_raw_payload(): void
    {
        Http::fake(['webapi.bps.go.id/*' => Http::response([
            'status' => 'OK', 'data-availability' => 'available',
            'datacontent' => ['1400702121230' => 71.11],
        ], 200)]);

        $client = $this->app->make(BpsApiClient::class);
        $first = $client->get('/list/model/data', ['domain' => '0000', 'var' => '70', 'th' => '123']);
        $cached = $client->get('/list/model/data', ['domain' => '0000', 'var' => '70', 'th' => '123']);

        $this->assertSame(71.11, $first->raw['datacontent']['1400702121230']);
        $this->assertSame($first->raw, $cached->raw);
        Http::assertSentCount(1);
    }

    public function test_cache_hit_skips_http(): void
    {
        Http::fake(['webapi.bps.go.id/*' => Http::response([
            'status' => 'OK', 'data-availability' => 'available',
            'data' => [['page' => 1, 'pages' => 1, 'total' => 1], [['domain_id' => '0000']]],
        ], 200)]);

        $c = $this->app->make(BpsApiClient::class);
        $first = $c->get('/domain/model/domain', ['type' => 'all']);
        $second = $c->get('/domain/model/domain', ['type' => 'all']);

        $this->assertTrue($first->isOk && $second->isOk);
        $this->assertSame('0000', $second->rows[0]['domain_id']);
        Http::assertSentCount(1);
    }

    public function test_error_response_not_cached(): void
    {
        Http::fake(['webapi.bps.go.id/*' => Http::response([
            'status' => 'Error', 'message' => 'Parameter Type is Missing.',
        ], 200)]);

        $c = $this->app->make(BpsApiClient::class);
        $c->get('/domain/model/domain', []);
        $c->get('/domain/model/domain', []);

        Http::assertSentCount(2);
    }

    public function test_timeout_throws_exception(): void
    {
        Http::fake(['webapi.bps.go.id/*' => function () {
            throw new ConnectionException('timeout');
        }]);

        $this->expectException(BpsApiException::class);
        $this->app->make(BpsApiClient::class)->get('/domain/model/domain', ['type' => 'all']);
    }

    public function test_query_param_style_for_dataexim(): void
    {
        Http::fake(['webapi.bps.go.id/v1/api/dataexim*' => Http::response([
            'status' => 'OK', 'data-availability' => 'available',
            'data' => [['page' => 1, 'pages' => 1, 'total' => 1], [['value' => 1000]]],
        ], 200)]);

        $this->app->make(BpsApiClient::class)->getQuery('/dataexim', [
            'sumber' => '1', 'periode' => '2', 'kodehs' => '03', 'jenishs' => '1', 'Tahun' => '2019',
        ]);

        Http::assertSent(fn ($r) => str_contains($r->url(), 'dataexim')
            && str_contains($r->url(), 'sumber=1')
            && str_contains($r->url(), 'key=test-key-123'));
    }

    public function test_query_param_multi_hs_code_keeps_literal_semicolon(): void
    {
        Http::fake(['webapi.bps.go.id/v1/api/dataexim*' => Http::response([
            'status' => 'OK', 'data-availability' => 'available',
            'data' => [['page' => 1, 'pages' => 1, 'total' => 1], [['value' => 1000]]],
        ], 200)]);

        $this->app->make(BpsApiClient::class)->getQuery('/dataexim', [
            'sumber' => '1', 'periode' => '2', 'kodehs' => '01;02', 'jenishs' => '1', 'Tahun' => '2019',
        ]);

        Http::assertSent(fn ($r) => str_contains($r->url(), 'kodehs=01;02')
            && ! str_contains($r->url(), 'kodehs=01%3B02'));
    }
}
