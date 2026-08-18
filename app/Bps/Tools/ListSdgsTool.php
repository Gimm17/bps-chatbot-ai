<?php

namespace App\Bps\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

final class ListSdgsTool extends AbstractBpsTool implements Tool
{
    public function description(): string
    {
        return 'Daftar indikator SDGs BPS nasional. Domain otomatis 0000; goal dapat dipakai sebagai filter.';
    }

    public function handle(Request $request): string
    {
        $arguments = $request->all();

        return $this->list('/list/model/sdgs', [
            'domain' => '0000',
            ...$this->optional($arguments, ['goal', 'lang', 'page']),
        ], 'sdgs');
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'goal' => $schema->string()->description('Nomor tujuan SDGs'),
            'lang' => $schema->string()->enum(['ind', 'eng'])->description('Bahasa'),
            'page' => $schema->integer()->description('Halaman'),
        ];
    }
}
