<?php

namespace App\Bps\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

final class ListStatictablesTool extends AbstractBpsTool implements Tool
{
    public function description(): string
    {
        return 'Cari tabel statistik statis BPS berdasarkan domain, kata kunci, dan tahun.';
    }

    public function handle(Request $request): string
    {
        $arguments = $request->all();

        return $this->list('/list/model/statictable', [
            'domain' => (string) ($arguments['domain'] ?? ''),
            ...$this->optional($arguments, ['keyword', 'year', 'lang', 'page']),
        ], 'static_tables');
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'domain' => $schema->string()->required()->description('Domain id BPS'),
            'keyword' => $schema->string()->description('Kata kunci'),
            'year' => $schema->string()->description('Tahun'),
            'lang' => $schema->string()->enum(['ind', 'eng'])->description('Bahasa'),
            'page' => $schema->integer()->description('Halaman'),
        ];
    }
}
