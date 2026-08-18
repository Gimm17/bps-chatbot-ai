<?php

namespace App\Bps\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

final class DataeximTool extends AbstractBpsTool implements Tool
{
    public function description(): string
    {
        return 'Ambil data ekspor atau impor BPS menurut kode HS dan periode. Gunakan untuk statistik perdagangan luar negeri.';
    }

    public function handle(Request $request): string
    {
        $arguments = $request->all();

        return $this->list('/dataexim', [
            'sumber' => (string) ($arguments['sumber'] ?? ''),
            'periode' => (string) ($arguments['periode'] ?? ''),
            'kodehs' => (string) ($arguments['kodehs'] ?? ''),
            'jenishs' => (string) ($arguments['jenishs'] ?? ''),
            'Tahun' => (string) ($arguments['Tahun'] ?? ''),
        ], 'trade', true);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'sumber' => $schema->string()->enum(['1', '2'])->required()->description('1 ekspor, 2 impor'),
            'periode' => $schema->string()->enum(['1', '2'])->required()->description('1 bulanan, 2 tahunan'),
            'kodehs' => $schema->string()->required()->description('Kode HS; pisahkan beberapa kode dengan titik koma'),
            'jenishs' => $schema->string()->enum(['1', '2'])->required()->description('1 HS 2-digit, 2 HS 8-digit'),
            'Tahun' => $schema->string()->required()->description('Tahun data'),
        ];
    }
}
