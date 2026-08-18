<?php

namespace App\Bps\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

final class ListInfographicsTool extends AbstractBpsTool implements Tool
{
    public function description(): string
    {
        return 'Cari infografis resmi BPS untuk penyajian visual statistik berdasarkan domain dan kata kunci.';
    }

    public function handle(Request $request): string
    {
        $arguments = $request->all();

        return $this->list('/list/model/infographic', [
            'domain' => (string) ($arguments['domain'] ?? ''),
            ...$this->optional($arguments, ['keyword', 'lang', 'page']),
        ], 'infographics');
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'domain' => $schema->string()->required()->description('Domain id BPS'),
            'keyword' => $schema->string()->description('Kata kunci'),
            'lang' => $schema->string()->enum(['ind', 'eng'])->description('Bahasa'),
            'page' => $schema->integer()->description('Halaman'),
        ];
    }
}
