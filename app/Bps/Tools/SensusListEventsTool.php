<?php

namespace App\Bps\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

final class SensusListEventsTool extends AbstractBpsTool implements Tool
{
    public function description(): string
    {
        return 'Daftar kegiatan sensus BPS yang tersedia. Gunakan sebelum meminta dataset sensus bila kode kegiatan belum diketahui.';
    }

    public function handle(Request $request): string
    {
        return $this->list('/interoperabilitas/datasource/sensus/id/37', [], 'events');
    }

    public function schema(JsonSchema $schema): array
    {
        return [];
    }
}
