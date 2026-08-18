<?php

namespace App\Bps\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

final class SimdasiTablesTool extends AbstractBpsTool implements Tool
{
    public function description(): string
    {
        return 'Daftar tabel SIMDASI BPS untuk suatu wilayah. Gunakan hasil id_tabel sebelum mengambil detail.';
    }

    public function handle(Request $request): string
    {
        $arguments = $request->all();

        return $this->list('/interoperabilitas/datasource/simdasi/id/23', [
            'wilayah' => (string) ($arguments['wilayah'] ?? ''),
        ], 'tables');
    }

    public function schema(JsonSchema $schema): array
    {
        return ['wilayah' => $schema->string()->required()->description('Kode wilayah')];
    }
}
