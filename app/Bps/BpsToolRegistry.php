<?php

namespace App\Bps;

use App\Bps\Tools\DataeximTool;
use App\Bps\Tools\GetDynamicDataTool;
use App\Bps\Tools\GetGlosariumTool;
use App\Bps\Tools\GetPressreleaseTool;
use App\Bps\Tools\GetPublicationTool;
use App\Bps\Tools\GetStatictableTool;
use App\Bps\Tools\ListDomainsTool;
use App\Bps\Tools\ListIndicatorsTool;
use App\Bps\Tools\ListInfographicsTool;
use App\Bps\Tools\ListPressreleasesTool;
use App\Bps\Tools\ListPublicationsTool;
use App\Bps\Tools\ListSdgsTool;
use App\Bps\Tools\ListStatictablesTool;
use App\Bps\Tools\ListUnitsTool;
use App\Bps\Tools\ListVarsTool;
use App\Bps\Tools\SensusDataTool;
use App\Bps\Tools\SensusListEventsTool;
use App\Bps\Tools\SimdasiDetailTool;
use App\Bps\Tools\SimdasiTablesTool;
use Laravel\Ai\Contracts\Tool;

/**
 * Intent (dari ScopeGuard) → subset tool BPS relevan.
 * Pre-filter jaga context LLM ringan & cap 4 cukup.
 */
final class BpsToolRegistry
{
    public function __construct(
        private readonly BpsApiClient $client,
    ) {}

    /** @return list<Tool> */
    public function forIntent(string $intent): array
    {
        $classes = $this->mapping()[$intent] ?? [];
        $tools = [];
        foreach ($classes as $class) {
            $tools[] = new $class($this->client);
        }

        return $tools;
    }

    /** @return array<string, list<class-string<Tool>>> */
    private function mapping(): array
    {
        return [
            'definition' => [GetGlosariumTool::class],
            'numeric_statistic' => [
                ListDomainsTool::class, ListVarsTool::class, ListIndicatorsTool::class,
                GetDynamicDataTool::class, DataeximTool::class, ListSdgsTool::class,
                SensusListEventsTool::class, SensusDataTool::class,
                SimdasiTablesTool::class, SimdasiDetailTool::class,
            ],
            'publication' => [
                ListPublicationsTool::class, GetPublicationTool::class,
                ListPressreleasesTool::class, GetPressreleaseTool::class,
            ],
            'metadata_methodology' => [
                GetGlosariumTool::class, ListStatictablesTool::class, GetStatictableTool::class,
                ListUnitsTool::class, ListVarsTool::class,
            ],
            'navigation' => [
                ListDomainsTool::class, ListPublicationsTool::class,
                ListPressreleasesTool::class, ListInfographicsTool::class,
            ],
            'bps_service' => [],
        ];
    }
}
