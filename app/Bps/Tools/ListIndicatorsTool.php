<?php

namespace App\Bps\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

final class ListIndicatorsTool extends AbstractBpsTool implements Tool
{
    public function description(): string
    {
        return 'Daftar indikator strategis BPS dalam domain. Gunakan untuk angka indikator siap pakai.';
    }

    public function handle(Request $request): string
    {
        $arguments = $request->all();

        return $this->list('/list/model/indicators', [
            'domain' => (string) ($arguments['domain'] ?? ''),
            ...$this->optional($arguments, ['var']),
        ], 'indicators');
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'domain' => $schema->string()->required()->description('Domain id BPS'),
            'var' => $schema->string()->description('Variable id opsional'),
        ];
    }
}
