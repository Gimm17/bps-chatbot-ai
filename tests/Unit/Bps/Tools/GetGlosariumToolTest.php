<?php

namespace Tests\Unit\Bps\Tools;

use App\Bps\Tools\GetGlosariumTool;
use Illuminate\Support\Facades\Http;

class GetGlosariumToolTest extends BpsToolTestCase
{
    public function test_lists_glossary_terms(): void
    {
        Http::fake(['webapi.bps.go.id/*' => Http::response([
            'status' => 'OK', 'data-availability' => 'available',
            'data' => [['pages' => 1, 'total' => 1], [[
                'id' => 9, 'term' => 'Angkatan Kerja', 'definition' => 'Penduduk usia kerja yang aktif.',
            ]]],
        ])]);
        $result = json_decode((new GetGlosariumTool($this->client()))->handle($this->request(['prefix' => 'A'])), true);
        $this->assertSame('ok', $result['status']);
        $this->assertSame('Angkatan Kerja', $result['terms'][0]['term']);
        $this->assertSame('Penduduk usia kerja yang aktif.', $result['terms'][0]['definition']);
        Http::assertSent(fn ($request) => str_contains($request->url(), '/list/model/glosarium'));
    }

    public function test_gets_glossary_detail_when_id_is_provided(): void
    {
        Http::fake(['webapi.bps.go.id/*' => Http::response([
            'status' => 'OK', 'data-availability' => 'available',
            'data' => [['pages' => 1, 'total' => 1], [[
                'glosarium_id' => 9, 'istilah' => 'Angkatan Kerja', 'def_text' => 'Definisi detail.',
            ]]],
        ])]);
        $result = json_decode((new GetGlosariumTool($this->client()))->handle($this->request(['id' => '9', 'lang' => 'ind'])), true);
        $this->assertSame('Angkatan Kerja', $result['terms'][0]['term']);
        Http::assertSent(fn ($request) => str_contains($request->url(), '/view/model/glosarium/id/9/lang/ind'));
    }

    public function test_unrecognized_row_keys_preserve_compact_raw_row(): void
    {
        Http::fake(['webapi.bps.go.id/*' => Http::response([
            'status' => 'OK', 'data-availability' => 'available',
            'data' => [['pages' => 1, 'total' => 1], [['kode' => 'X', 'makna' => 'Y']]],
        ])]);
        $result = json_decode((new GetGlosariumTool($this->client()))->handle($this->request()), true);
        $this->assertSame(['kode' => 'X', 'makna' => 'Y'], $result['terms'][0]['raw']);
    }

    public function test_non_ok_response_returns_error_json(): void
    {
        Http::fake(['webapi.bps.go.id/*' => Http::response(['status' => 'Error', 'message' => 'Please re-check your URL Request.'], 200)]);
        $result = json_decode((new GetGlosariumTool($this->client()))->handle($this->request()), true);
        $this->assertSame('error', $result['status']);
    }

    public function test_schema_exposes_documented_arguments(): void
    {
        $schema = (new GetGlosariumTool($this->client()))->schema($this->schema());
        foreach (['id', 'lang', 'prefix', 'page', 'perpage'] as $key) {
            $this->assertArrayHasKey($key, $schema);
        }
    }
}
