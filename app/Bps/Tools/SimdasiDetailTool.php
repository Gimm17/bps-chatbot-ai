<?php

namespace App\Bps\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

final class SimdasiDetailTool extends AbstractBpsTool implements Tool
{
    public function description(): string
    {
        return 'Ambil detail data tabel SIMDASI BPS berdasarkan wilayah, tahun, dan id tabel.';
    }

    public function handle(Request $request): string
    {
        $arguments = $request->all();

        return $this->list('/interoperabilitas/datasource/simdasi/id/25', [
            'wilayah' => (string) ($arguments['wilayah'] ?? ''),
            'Tahun' => (string) ($arguments['Tahun'] ?? ''),
            'id_tabel' => (string) ($arguments['id_tabel'] ?? ''),
        ], 'data');
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'wilayah' => $schema->string()->required()->description('Kode wilayah'),
            'Tahun' => $schema->string()->required()->description('Tahun data'),
            'id_tabel' => $schema->string()->required()->description('ID tabel SIMDASI'),
        ];
    }
}
