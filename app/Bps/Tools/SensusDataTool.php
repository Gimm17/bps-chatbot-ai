<?php

namespace App\Bps\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

final class SensusDataTool extends AbstractBpsTool implements Tool
{
    public function description(): string
    {
        return 'Ambil data sensus BPS berdasarkan kegiatan, wilayah sensus, dan dataset yang sudah dipilih.';
    }

    public function handle(Request $request): string
    {
        $arguments = $request->all();

        return $this->list('/interoperabilitas/datasource/sensus/id/41', [
            'Kegiatan' => (string) ($arguments['Kegiatan'] ?? ''),
            'Wilayah_sensus' => (string) ($arguments['Wilayah_sensus'] ?? ''),
            'Dataset' => (string) ($arguments['Dataset'] ?? ''),
        ], 'data');
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'Kegiatan' => $schema->string()->required()->description('Kode kegiatan sensus'),
            'Wilayah_sensus' => $schema->string()->required()->description('Kode wilayah sensus'),
            'Dataset' => $schema->string()->required()->description('Kode dataset sensus'),
        ];
    }
}
