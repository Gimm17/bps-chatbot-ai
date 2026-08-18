<?php

namespace Tests\Unit\Bps;

use App\Bps\BpsResponse;
use PHPUnit\Framework\TestCase;

class BpsResponseTest extends TestCase
{
    public function test_parse_ok_with_available_data(): void
    {
        $raw = [
            'status' => 'OK', 'data-availability' => 'available',
            'datacontent' => ['1400702121230' => 71.11],
            'data' => [['page' => 1, 'pages' => 1, 'total' => 2],
                [['domain_id' => '0000', 'domain_name' => 'Pusat'],
                    ['domain_id' => '1100', 'domain_name' => 'Aceh']]],
        ];

        $resp = BpsResponse::parse($raw, 200);

        $this->assertTrue($resp->isOk);
        $this->assertNull($resp->errorMessage);
        $this->assertCount(2, $resp->rows);
        $this->assertSame('0000', $resp->rows[0]['domain_id']);
        $this->assertSame(2, $resp->total);
        $this->assertSame(['1400702121230' => 71.11], $resp->raw['datacontent']);
    }

    public function test_parse_error_status_not_ok(): void
    {
        $body = ['status' => 'Error', 'message' => 'Parameter Type is Missing.'];
        $resp = BpsResponse::parse($body, 200);

        $this->assertFalse($resp->isOk);
        $this->assertSame('Parameter Type is Missing.', $resp->errorMessage);
        $this->assertSame([], $resp->rows);
        $this->assertSame($body, $resp->raw);
    }

    public function test_parse_data_availability_na(): void
    {
        $resp = BpsResponse::parse(['status' => 'OK', 'data-availability' => 'na', 'data' => []], 200);
        $this->assertFalse($resp->isOk);
    }

    public function test_parse_empty_body(): void
    {
        $resp = BpsResponse::parse([], 500);
        $this->assertFalse($resp->isOk);
        $this->assertSame([], $resp->rows);
    }

    public function test_to_json_roundtrip(): void
    {
        $resp = BpsResponse::parse([
            'status' => 'OK', 'data-availability' => 'available',
            'datacontent' => ['key' => 99],
            'data' => [['page' => 1, 'pages' => 1, 'total' => 1], [['id' => 'x']]],
        ], 200);

        $restored = BpsResponse::fromCached($resp->toJson());

        $this->assertTrue($restored->isOk);
        $this->assertSame('x', $restored->rows[0]['id']);
        $this->assertSame(['key' => 99], $restored->raw['datacontent']);
    }

    public function test_parse_error_uses_message2_fallback(): void
    {
        $resp = BpsResponse::parse(['status' => 'Error', 'message2' => 'Fallback detail'], 200);

        $this->assertFalse($resp->isOk);
        $this->assertSame('Fallback detail', $resp->errorMessage);
    }

    public function test_error_path_roundtrip_preserves_error_message(): void
    {
        $resp = BpsResponse::parse(['status' => 'Error', 'message' => 'Boom'], 200);

        $restored = BpsResponse::fromCached($resp->toJson());

        $this->assertFalse($restored->isOk);
        $this->assertSame('Boom', $restored->errorMessage);
    }
}
