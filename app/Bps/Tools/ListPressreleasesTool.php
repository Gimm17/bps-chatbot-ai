<?php

namespace App\Bps\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

final class ListPressreleasesTool extends AbstractBpsTool implements Tool
{
    public function description(): string
    {
        return 'Cari daftar berita resmi statistik BPS untuk rilis indikator dan perkembangan terbaru.';
    }

    public function handle(Request $request): string
    {
        $arguments = $request->all();

        return $this->list('/list/model/pressrelease', [
            'domain' => (string) ($arguments['domain'] ?? ''),
            ...$this->optional($arguments, ['keyword', 'year', 'month', 'lang', 'page']),
        ], 'press_releases');
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'domain' => $schema->string()->required()->description('Domain id BPS'),
            'keyword' => $schema->string()->description('Kata kunci'),
            'year' => $schema->string()->description('Tahun'),
            'month' => $schema->string()->description('Bulan'),
            'lang' => $schema->string()->enum(['ind', 'eng'])->description('Bahasa'),
            'page' => $schema->integer()->description('Halaman'),
        ];
    }
}
