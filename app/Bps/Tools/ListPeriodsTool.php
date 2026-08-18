<?php

namespace App\Bps\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

final class ListPeriodsTool extends AbstractBpsTool implements Tool
{
    public function description(): string
    {
        return 'Daftar periode BPS untuk mendapatkan period id (th) sebelum mengambil data dinamis.';
    }

    public function handle(Request $request): string
    {
        $arguments = $request->all();

        return $this->list('/list/model/th', [
            'domain' => (string) ($arguments['domain'] ?? ''),
            ...$this->optional($arguments, ['var']),
        ], 'periods');
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'domain' => $schema->string()->required()->description('Domain id BPS'),
            'var' => $schema->string()->description('Variable id opsional'),
        ];
    }
}
