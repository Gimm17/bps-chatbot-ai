<?php

namespace Tests\Unit\Bps\Tools;

use App\Bps\Tools\GetDynamicDataTool;
use Illuminate\Support\Facades\Http;

class GetDynamicDataToolTest extends BpsToolTestCase
{
    private array $verifiedBody = [
        'status' => 'OK',
        'data-availability' => 'available',
        'last_update' => '2024-01-01',
        'subject' => [['val' => 2, 'label' => 'Komunikasi']],
        'var' => [['val' => 70, 'label' => 'Persentase Penduduk', 'unit' => '', 'subj' => 'Komunikasi', 'def' => '', 'decimal' => 2, 'note' => 'Resmi']],
        'turvar' => [['val' => 211, 'label' => 'Laki-laki'], ['val' => 212, 'label' => 'Perempuan']],
        'labelvervar' => 'Provinsi',
        'vervar' => [['val' => 1100, 'label' => 'ACEH'], ['val' => 1200, 'label' => 'SUMATERA UTARA']],
        'tahun' => [['val' => 123, 'label' => '2023']],
        'turtahun' => [['val' => 0, 'label' => 'Tahun']],
        'datacontent' => ['1400702121230' => 71.11, '6200702121230' => 68.75, '1800702111230' => 72.59],
    ];

    public function test_maps_verified_top_level_dynamic_shape_losslessly(): void
    {
        Http::fake(['webapi.bps.go.id/*' => Http::response($this->verifiedBody)]);
        $tool = new GetDynamicDataTool($this->client());
        $result = json_decode($tool->handle($this->request(['domain' => '0000', 'var' => '70', 'th' => '123'])), true);

        $this->assertSame('ok', $result['status']);
        $this->assertSame('70', $result['var_id']);
        $this->assertSame('123', $result['period_id']);
        $this->assertContains(['key' => '1400702121230', 'value' => 71.11], $result['values']);
        $this->assertSame('Persentase Penduduk', $result['variable'][0]['label']);
        $this->assertSame('ACEH', $result['vertical_variables'][0]['label']);
        $this->assertSame('Perempuan', $result['derived_variables'][1]['label']);
        $this->assertSame('2023', $result['periods'][0]['label']);
        $this->assertSame('Tahun', $result['derived_periods'][0]['label']);
        $this->assertSame('Komunikasi', $result['subjects'][0]['label']);
        $this->assertSame('Provinsi', $result['vertical_variable_label']);
    }

    public function test_scalar_datacontent_returns_no_values(): void
    {
        Http::fake(['webapi.bps.go.id/*' => Http::response(array_replace($this->verifiedBody, ['datacontent' => 'invalid']))]);

        $result = json_decode((new GetDynamicDataTool($this->client()))->handle($this->request(['domain' => '0000', 'var' => '70', 'th' => '123'])), true);

        $this->assertSame([], $result['values']);
        $this->assertSame(0, $result['returned']);
        $this->assertFalse($result['truncated']);
    }

    public function test_bounds_dynamic_values_and_reports_truncation(): void
    {
        $content = [];
        foreach (range(1, 101) as $id) {
            $content[(string) $id] = $id;
        }
        Http::fake(['webapi.bps.go.id/*' => Http::response(array_replace($this->verifiedBody, ['datacontent' => $content]))]);

        $result = json_decode((new GetDynamicDataTool($this->client()))->handle($this->request(['domain' => '0000', 'var' => '70', 'th' => '123'])), true);

        $this->assertCount(100, $result['values']);
        $this->assertSame(101, $result['total']);
        $this->assertSame(100, $result['returned']);
        $this->assertTrue($result['truncated']);
    }

    public function test_non_ok_response_returns_error_json(): void
    {
        Http::fake(['webapi.bps.go.id/*' => Http::response(['status' => 'Error', 'message' => 'Bad data'], 200)]);
        $result = json_decode((new GetDynamicDataTool($this->client()))->handle($this->request(['domain' => '0000', 'var' => '70', 'th' => '123'])), true);
        $this->assertSame('error', $result['status']);
    }

    public function test_schema_requires_domain_var_and_th_and_has_optional_filters(): void
    {
        $schema = (new GetDynamicDataTool($this->client()))->schema($this->schema());
        foreach (['domain', 'var', 'th'] as $key) {
            $this->assertTrue((new \ReflectionProperty($schema[$key], 'required'))->getValue($schema[$key]));
        }
        foreach (['vervar', 'turvar', 'turth'] as $key) {
            $this->assertArrayHasKey($key, $schema);
        }
    }
}
