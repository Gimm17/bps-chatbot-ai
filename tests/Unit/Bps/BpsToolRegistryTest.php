<?php

namespace Tests\Unit\Bps;

use App\Bps\BpsApiClient;
use App\Bps\BpsToolRegistry;
use App\Bps\Tools\DataeximTool;
use App\Bps\Tools\GetDynamicDataTool;
use App\Bps\Tools\GetGlosariumTool;
use App\Bps\Tools\GetPublicationTool;
use App\Bps\Tools\ListIndicatorsTool;
use App\Bps\Tools\ListPublicationsTool;
use App\Bps\Tools\SensusDataTool;
use Tests\TestCase;

class BpsToolRegistryTest extends TestCase
{
    private function registry(): BpsToolRegistry
    {
        return new BpsToolRegistry($this->app->make(BpsApiClient::class));
    }

    public function test_definition_returns_glosarium_only(): void
    {
        $tools = $this->registry()->forIntent('definition');
        $this->assertCount(1, $tools);
        $this->assertInstanceOf(GetGlosariumTool::class, $tools[0]);
    }

    public function test_numeric_statistic_includes_core_data_tools(): void
    {
        $classes = array_map(fn ($t) => $t::class, $this->registry()->forIntent('numeric_statistic'));
        $this->assertContains(GetDynamicDataTool::class, $classes);
        $this->assertContains(ListIndicatorsTool::class, $classes);
        $this->assertContains(DataeximTool::class, $classes);
        $this->assertContains(SensusDataTool::class, $classes);
    }

    public function test_bps_service_returns_empty(): void
    {
        $this->assertSame([], $this->registry()->forIntent('bps_service'));
    }

    public function test_publication_has_list_and_get(): void
    {
        $classes = array_map(fn ($t) => $t::class, $this->registry()->forIntent('publication'));
        $this->assertContains(ListPublicationsTool::class, $classes);
        $this->assertContains(GetPublicationTool::class, $classes);
    }
}
