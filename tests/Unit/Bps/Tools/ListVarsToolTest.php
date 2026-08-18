<?php

namespace Tests\Unit\Bps\Tools;

use App\Bps\Tools\ListVarsTool;
use Illuminate\Support\Facades\Http;

class ListVarsToolTest extends BpsToolTestCase
{
    public function test_lists_variables_with_actual_catalog_fields(): void
    {
        Http::fake(['webapi.bps.go.id/*' => Http::response([
            'status' => 'OK', 'data-availability' => 'available',
            'data' => [['pages' => 1, 'total' => 1], [[
                'var_id' => 70, 'title' => 'Persentase Penduduk', 'unit' => 'Tidak Ada Satuan',
                'sub_id' => 2, 'sub_name' => 'Komunikasi', 'def' => '', 'notes' => 'Catatan resmi',
            ]]],
        ])]);

        $tool = new ListVarsTool($this->client());
        $result = json_decode($tool->handle($this->request(['domain' => '0000'])), true);

        $this->assertStringContainsString('var id', strtolower($tool->description()));
        $this->assertSame('ok', $result['status']);
        $this->assertSame(70, $result['variables'][0]['var_id']);
        $this->assertSame('Komunikasi', $result['variables'][0]['sub_name']);
        $this->assertSame('Catatan resmi', $result['variables'][0]['notes']);
    }

    public function test_schema_requires_domain_and_exposes_optional_filters(): void
    {
        $schema = (new ListVarsTool($this->client()))->schema($this->schema());
        $this->assertTrue((new \ReflectionProperty($schema['domain'], 'required'))->getValue($schema['domain']));
        foreach (['subject', 'lang', 'year', 'page'] as $key) {
            $this->assertArrayHasKey($key, $schema);
        }
    }

    public function test_bounds_variable_results_and_reports_truncation(): void
    {
        $rows = array_map(fn ($id) => ['var_id' => $id, 'title' => "Variable {$id}"], range(1, 101));
        Http::fake(['webapi.bps.go.id/*' => Http::response([
            'status' => 'OK', 'data-availability' => 'available',
            'data' => [['pages' => 1, 'total' => 101], $rows],
        ])]);

        $result = json_decode((new ListVarsTool($this->client()))->handle($this->request(['domain' => '0000'])), true);

        $this->assertCount(100, $result['variables']);
        $this->assertSame(100, $result['returned']);
        $this->assertTrue($result['truncated']);
    }

    public function test_non_ok_response_returns_error_json(): void
    {
        Http::fake(['webapi.bps.go.id/*' => Http::response(['status' => 'Error', 'message' => 'Bad variable'], 200)]);
        $result = json_decode((new ListVarsTool($this->client()))->handle($this->request(['domain' => '0000'])), true);
        $this->assertSame('error', $result['status']);
    }
}
