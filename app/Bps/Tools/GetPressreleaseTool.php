<?php

namespace App\Bps\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

final class GetPressreleaseTool extends AbstractBpsTool implements Tool
{
    public function description(): string
    {
        return 'Ambil detail berita resmi statistik BPS berdasarkan id dari hasil pencarian press release.';
    }

    public function handle(Request $request): string
    {
        $arguments = $request->all();

        return $this->detail('/view/model/pressrelease', [
            'domain' => (string) ($arguments['domain'] ?? ''),
            'lang' => (string) ($arguments['lang'] ?? 'ind'),
            'id' => (string) ($arguments['id'] ?? ''),
        ], 'press_release');
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'domain' => $schema->string()->required()->description('Domain id BPS'),
            'id' => $schema->string()->required()->description('Press release id'),
            'lang' => $schema->string()->enum(['ind', 'eng'])->description('Bahasa'),
        ];
    }
}
