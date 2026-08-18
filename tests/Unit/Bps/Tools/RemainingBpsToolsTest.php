<?php

namespace Tests\Unit\Bps\Tools;

use App\Bps\Tools\DataeximTool;
use App\Bps\Tools\GetPressreleaseTool;
use App\Bps\Tools\GetPublicationTool;
use App\Bps\Tools\GetStatictableTool;
use App\Bps\Tools\ListIndicatorsTool;
use App\Bps\Tools\ListInfographicsTool;
use App\Bps\Tools\ListPeriodsTool;
use App\Bps\Tools\ListPressreleasesTool;
use App\Bps\Tools\ListPublicationsTool;
use App\Bps\Tools\ListSdgsTool;
use App\Bps\Tools\ListStatictablesTool;
use App\Bps\Tools\ListSubcatsTool;
use App\Bps\Tools\ListSubjectsTool;
use App\Bps\Tools\ListTurthsTool;
use App\Bps\Tools\ListTurvarsTool;
use App\Bps\Tools\ListUnitsTool;
use App\Bps\Tools\ListVervarsTool;
use App\Bps\Tools\SensusDataTool;
use App\Bps\Tools\SensusListEventsTool;
use App\Bps\Tools\SimdasiDetailTool;
use App\Bps\Tools\SimdasiTablesTool;
use Illuminate\Support\Facades\Http;
use Laravel\Ai\Contracts\Tool;
use PHPUnit\Framework\Attributes\DataProvider;

class RemainingBpsToolsTest extends BpsToolTestCase
{
    #[DataProvider('listToolProvider')]
    public function test_list_tool_calls_endpoint_and_returns_bounded_rows(
        string $class,
        string $endpoint,
        array $arguments,
        string $resultKey,
    ): void {
        $rows = array_map(fn ($id) => ['id' => $id, 'title' => "Row {$id}"], range(1, 101));
        Http::fake(['webapi.bps.go.id/*' => Http::response([
            'status' => 'OK', 'data-availability' => 'available',
            'data' => [['pages' => 1, 'total' => 101], $rows],
        ])]);

        $tool = new $class($this->client());
        $result = json_decode($tool->handle($this->request($arguments)), true);

        $this->assertInstanceOf(Tool::class, $tool);
        $this->assertSame('ok', $result['status']);
        $this->assertCount(100, $result[$resultKey]);
        $this->assertSame(100, $result['returned']);
        $this->assertTrue($result['truncated']);
        Http::assertSent(fn ($request) => str_contains($request->url(), $endpoint));
    }

    public static function listToolProvider(): array
    {
        return [
            'subjects' => [ListSubjectsTool::class, '/list/model/subject/domain/0000', ['domain' => '0000'], 'subjects'],
            'subcats' => [ListSubcatsTool::class, '/list/model/subcat/domain/0000', ['domain' => '0000'], 'subcategories'],
            'vervars' => [ListVervarsTool::class, '/list/model/vervar/domain/0000', ['domain' => '0000'], 'vertical_variables'],
            'periods' => [ListPeriodsTool::class, '/list/model/th/domain/0000/var/70', ['domain' => '0000', 'var' => '70'], 'periods'],
            'turvars' => [ListTurvarsTool::class, '/list/model/turvar/domain/0000/var/70', ['domain' => '0000', 'var' => '70'], 'derived_variables'],
            'turths' => [ListTurthsTool::class, '/list/model/turth/domain/0000/var/70', ['domain' => '0000', 'var' => '70'], 'derived_periods'],
            'units' => [ListUnitsTool::class, '/list/model/unit/domain/0000', ['domain' => '0000'], 'units'],
            'indicators' => [ListIndicatorsTool::class, '/list/model/indicators/domain/0000', ['domain' => '0000'], 'indicators'],
            'publications' => [ListPublicationsTool::class, '/list/model/publication/domain/0000', ['domain' => '0000'], 'publications'],
            'press releases' => [ListPressreleasesTool::class, '/list/model/pressrelease/domain/0000', ['domain' => '0000'], 'press_releases'],
            'static tables' => [ListStatictablesTool::class, '/list/model/statictable/domain/0000', ['domain' => '0000'], 'static_tables'],
            'infographics' => [ListInfographicsTool::class, '/list/model/infographic/domain/0000', ['domain' => '0000'], 'infographics'],
            'sdgs' => [ListSdgsTool::class, '/list/model/sdgs/domain/0000', [], 'sdgs'],
        ];
    }

    #[DataProvider('detailToolProvider')]
    public function test_detail_tool_calls_view_endpoint_and_preserves_official_fields(
        string $class,
        string $endpoint,
        array $arguments,
        string $resultKey,
    ): void {
        Http::fake(['webapi.bps.go.id/*' => Http::response([
            'status' => 'OK', 'data-availability' => 'available',
            'data' => [['pages' => 1, 'total' => 1], [[
                'id' => 'abc', 'pub_id' => 'abc', 'title' => 'Official title',
                'pdf' => 'https://bps.go.id/file.pdf', 'abstract' => 'Official abstract',
                'rl_date' => '2026-08-18',
            ]]],
        ])]);

        $tool = new $class($this->client());
        $result = json_decode($tool->handle($this->request($arguments)), true);

        $this->assertSame('ok', $result['status']);
        $this->assertSame('Official title', $result[$resultKey]['title']);
        $this->assertSame('https://bps.go.id/file.pdf', $result[$resultKey]['pdf']);
        Http::assertSent(fn ($request) => str_contains($request->url(), $endpoint));
    }

    public static function detailToolProvider(): array
    {
        return [
            'publication' => [GetPublicationTool::class, '/view/model/publication/domain/0000/lang/ind/id/abc', ['domain' => '0000', 'id' => 'abc'], 'publication'],
            'press release' => [GetPressreleaseTool::class, '/view/model/pressrelease/domain/0000/lang/ind/id/abc', ['domain' => '0000', 'id' => 'abc'], 'press_release'],
            'static table' => [GetStatictableTool::class, '/view/model/statictable/domain/0000/lang/ind/id/abc', ['domain' => '0000', 'id' => 'abc'], 'static_table'],
        ];
    }

    public function test_dataexim_uses_query_auth_and_enum_schema(): void
    {
        Http::fake(['webapi.bps.go.id/*' => Http::response([
            'status' => 'OK', 'data-availability' => 'available',
            'data' => [['pages' => 1, 'total' => 1], [['value' => 1000]]],
        ])]);

        $tool = new DataeximTool($this->client());
        $result = json_decode($tool->handle($this->request([
            'sumber' => '1', 'periode' => '2', 'kodehs' => '01;02', 'jenishs' => '1', 'Tahun' => '2025',
        ])), true);
        $schema = $tool->schema($this->schema());

        $this->assertSame('ok', $result['status']);
        $this->assertSame(['1', '2'], $schema['sumber']->toArray()['enum']);
        $this->assertSame(['1', '2'], $schema['periode']->toArray()['enum']);
        Http::assertSent(fn ($request) => str_contains($request->url(), '/dataexim?')
            && str_contains($request->url(), 'kodehs=01;02')
            && str_contains($request->url(), 'key=test-key-123'));
    }

    #[DataProvider('interoperabilityToolProvider')]
    public function test_interoperability_tool_calls_documented_endpoint(
        string $class,
        string $endpoint,
        array $arguments,
        string $resultKey,
    ): void {
        Http::fake(['webapi.bps.go.id/*' => Http::response([
            'status' => 'OK', 'data-availability' => 'available',
            'data' => [['pages' => 1, 'total' => 1], [['id' => 1, 'name' => 'Official row']]],
        ])]);

        $result = json_decode((new $class($this->client()))->handle($this->request($arguments)), true);

        $this->assertSame('ok', $result['status']);
        $this->assertCount(1, $result[$resultKey]);
        Http::assertSent(fn ($request) => str_contains($request->url(), $endpoint));
    }

    public static function interoperabilityToolProvider(): array
    {
        return [
            'sensus events' => [SensusListEventsTool::class, '/interoperabilitas/datasource/sensus/id/37', [], 'events'],
            'sensus data' => [SensusDataTool::class, '/interoperabilitas/datasource/sensus/id/41', ['Kegiatan' => 'SP2020', 'Wilayah_sensus' => '0000', 'Dataset' => '1'], 'data'],
            'simdasi tables' => [SimdasiTablesTool::class, '/interoperabilitas/datasource/simdasi/id/23/wilayah/0000', ['wilayah' => '0000'], 'tables'],
            'simdasi detail' => [SimdasiDetailTool::class, '/interoperabilitas/datasource/simdasi/id/25/wilayah/0000/Tahun/2025/id_tabel/1', ['wilayah' => '0000', 'Tahun' => '2025', 'id_tabel' => '1'], 'data'],
        ];
    }

    public function test_nested_interoperability_error_is_not_reported_as_success(): void
    {
        Http::fake(['webapi.bps.go.id/*' => Http::response([
            'status' => 'OK', 'data-availability' => 'available',
            'data' => [['pages' => 1, 'total' => 1], [[
                'status' => 400, 'condition' => 'ERROR', 'message' => 'Invalid Parameter (wilayah)',
            ]]],
        ])]);

        $result = json_decode((new SimdasiTablesTool($this->client()))->handle($this->request(['wilayah' => '0000'])), true);

        $this->assertSame('error', $result['status']);
        $this->assertSame('Invalid Parameter (wilayah)', $result['message']);
    }

    public function test_truncation_does_not_trust_underreported_upstream_total(): void
    {
        $rows = array_map(fn ($id) => ['id' => $id], range(1, 101));
        Http::fake(['webapi.bps.go.id/*' => Http::response([
            'status' => 'OK', 'data-availability' => 'available',
            'data' => [['pages' => 1, 'total' => 0], $rows],
        ])]);

        $result = json_decode((new ListSubjectsTool($this->client()))->handle($this->request(['domain' => '0000'])), true);

        $this->assertSame(101, $result['total']);
        $this->assertSame(100, $result['returned']);
        $this->assertTrue($result['truncated']);
    }

    #[DataProvider('requiredSchemaProvider')]
    public function test_tool_schema_marks_required_arguments(string $class, array $required): void
    {
        $schema = (new $class($this->client()))->schema($this->schema());
        $actual = array_keys(array_filter(
            $schema,
            fn ($field) => (new \ReflectionProperty($field, 'required'))->getValue($field) === true,
        ));

        $this->assertSame($required, $actual);
    }

    public static function requiredSchemaProvider(): array
    {
        return [
            'subjects' => [ListSubjectsTool::class, ['domain']],
            'publication detail' => [GetPublicationTool::class, ['domain', 'id']],
            'trade' => [DataeximTool::class, ['sumber', 'periode', 'kodehs', 'jenishs', 'Tahun']],
            'sensus data' => [SensusDataTool::class, ['Kegiatan', 'Wilayah_sensus', 'Dataset']],
            'simdasi detail' => [SimdasiDetailTool::class, ['wilayah', 'Tahun', 'id_tabel']],
        ];
    }
}
