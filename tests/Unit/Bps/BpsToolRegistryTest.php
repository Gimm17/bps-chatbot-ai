<?php

namespace Tests\Unit\Bps;

use App\Bps\BpsApiClient;
use App\Bps\BpsToolRegistry;
use App\Bps\Tools\DataeximTool;
use App\Bps\Tools\GetDynamicDataTool;
use App\Bps\Tools\GetPublicationTool;
use App\Bps\Tools\ListIndicatorsTool;
use App\Bps\Tools\ListPeriodsTool;
use App\Bps\Tools\ListPublicationsTool;
use App\Bps\Tools\ListStatictablesTool;
use App\Bps\Tools\ListVarsTool;
use App\Bps\Tools\SensusDataTool;
use Tests\TestCase;

class BpsToolRegistryTest extends TestCase
{
    private function registry(): BpsToolRegistry
    {
        return new BpsToolRegistry($this->app->make(BpsApiClient::class));
    }

    public function test_definition_returns_catalog_tools_so_answers_are_bps_sourced(): void
    {
        // Definisi/konsep istilah BPS dicari via katalog variabel, indikator,
        // tabel statis, dan publikasi agar jawaban bersumber BPS (verified),
        // bukan fallback .md demo. Endpoint glosarium live memang unavailable,
        // tetapi istilah dapat dijelaskan dari sumber BPS lain.
        $classes = array_map(fn ($t) => $t::class, $this->registry()->forIntent('definition'));
        $this->assertContains(ListVarsTool::class, $classes);
        $this->assertContains(ListIndicatorsTool::class, $classes);
        $this->assertContains(ListStatictablesTool::class, $classes);
        $this->assertContains(ListPublicationsTool::class, $classes);
    }

    public function test_numeric_statistic_includes_core_data_tools(): void
    {
        $classes = array_map(fn ($t) => $t::class, $this->registry()->forIntent('numeric_statistic'));
        $this->assertContains(GetDynamicDataTool::class, $classes);
        $this->assertContains(ListIndicatorsTool::class, $classes);
        $this->assertContains(ListPeriodsTool::class, $classes);
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
