<?php

namespace App\Bps\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

final class ListVervarsTool extends AbstractBpsTool implements Tool
{
    public function description(): string
    {
        return 'Daftar variabel vertikal BPS untuk mengetahui wilayah atau dimensi pemecah data dinamis.';
    }

    public function handle(Request $request): string
    {
        $arguments = $request->all();

        return $this->list('/list/model/vervar', [
            'domain' => (string) ($arguments['domain'] ?? ''),
            ...$this->optional($arguments, ['lang']),
        ], 'vertical_variables');
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'domain' => $schema->string()->required()->description('Domain id BPS'),
            'lang' => $schema->string()->enum(['ind', 'eng'])->description('Bahasa'),
        ];
    }
}
