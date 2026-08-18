<?php

namespace Tests\Unit\Bps\Tools;

use App\Bps\Tools\ListDomainsTool;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

class ListDomainsToolTest extends BpsToolTestCase
{
    public function test_lists_domains_and_maps_official_fields(): void
    {
        Http::fake(['webapi.bps.go.id/*' => Http::response([
            'status' => 'OK', 'data-availability' => 'available',
            'data' => [['pages' => 1, 'total' => 1], [[
                'domain_id' => '3200', 'domain_name' => 'Jawa Barat',
                'domain_url' => 'https://jabar.bps.go.id', 'ignored' => 'x',
            ]]],
        ])]);

        $tool = new ListDomainsTool($this->client());
        $result = json_decode($tool->handle($this->request(['type' => 'prov'])), true);

        $this->assertStringContainsString('domain', strtolower($tool->description()));
        $this->assertSame('ok', $result['status']);
        $this->assertSame('3200', $result['domains'][0]['domain_id']);
        $this->assertSame('Jawa Barat', $result['domains'][0]['domain_name']);
        $this->assertSame('https://jabar.bps.go.id', $result['domains'][0]['domain_url']);
    }

    public function test_schema_contains_required_type(): void
    {
        $schema = (new ListDomainsTool($this->client()))->schema($this->schema());
        $this->assertArrayHasKey('type', $schema);
        $this->assertSame(['type'], array_keys(array_filter($schema, fn ($field) => (new \ReflectionProperty($field, 'required'))->getValue($field) === true)));
    }

    public function test_bps_api_exception_returns_safe_error_json(): void
    {
        Http::fake(['webapi.bps.go.id/*' => function () {
            throw new ConnectionException('secret transport detail');
        }]);
        $result = json_decode((new ListDomainsTool($this->client()))->handle($this->request(['type' => 'all'])), true);
        $this->assertSame('error', $result['status']);
        $this->assertSame('BPS API connection failed (timeout or network error).', $result['message']);
        $this->assertStringNotContainsString('secret transport detail', json_encode($result));
    }

    public function test_non_ok_response_returns_error_json(): void
    {
        Http::fake(['webapi.bps.go.id/*' => Http::response(['status' => 'Error', 'message' => 'Bad domain'], 200)]);
        $result = json_decode((new ListDomainsTool($this->client()))->handle($this->request(['type' => 'all'])), true);
        $this->assertSame('error', $result['status']);
        $this->assertSame('Bad domain', $result['message']);
    }
}
