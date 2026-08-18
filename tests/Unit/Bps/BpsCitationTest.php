<?php

namespace Tests\Unit\Bps;

use App\Bps\BpsCitation;
use App\Rag\Citation;
use PHPUnit\Framework\TestCase;

class BpsCitationTest extends TestCase
{
    public function test_from_bps_sources_maps_ids_to_verified_citations(): void
    {
        $sources = [
            '954' => new BpsCitation('954', 'Inflasi (IHK)', 'https://jabar.bps.go.id', 'Inflasi Jawa Barat 2023: 2.8%', 'Jawa Barat', '2023'),
            'pub-abc' => new BpsCitation('pub-abc', 'Publikasi X', 'https://webapi.bps.go.id/cover.php?f=x', null),
        ];

        $citations = Citation::fromBpsSources($sources, ['954', 'unknown-id']);

        $this->assertCount(1, $citations);
        $this->assertSame('954', $citations[0]->sourceId);
        $this->assertTrue($citations[0]->verified);
        $this->assertSame('https://jabar.bps.go.id', $citations[0]->url);
        $this->assertSame('Inflasi (IHK)', $citations[0]->title);
        $this->assertSame('Inflasi Jawa Barat 2023: 2.8%', $citations[0]->snippet);
    }

    public function test_from_bps_sources_dedupes(): void
    {
        $sources = ['1' => new BpsCitation('1', 'T', null, null)];
        $this->assertCount(1, Citation::fromBpsSources($sources, ['1', '1']));
    }

    public function test_from_bps_sources_skips_empty_and_unknown_ids(): void
    {
        $sources = ['1' => new BpsCitation('1', 'T', null, null)];

        $this->assertSame([], Citation::fromBpsSources($sources, ['', '  ', '999']));
    }
}
