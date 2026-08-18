<?php

namespace App\Bps\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

final class GetStatictableTool extends AbstractBpsTool implements Tool
{
    public function description(): string
    {
        return 'Ambil detail tabel statistik statis BPS berdasarkan domain dan id.';
    }

    public function handle(Request $request): string
    {
        $arguments = $request->all();

        return $this->detail('/view/model/statictable', [
            'domain' => (string) ($arguments['domain'] ?? ''),
            'lang' => (string) ($arguments['lang'] ?? 'ind'),
            'id' => (string) ($arguments['id'] ?? ''),
        ], 'static_table');
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'domain' => $schema->string()->required()->description('Domain id BPS'),
            'id' => $schema->string()->required()->description('Static table id'),
            'lang' => $schema->string()->enum(['ind', 'eng'])->description('Bahasa'),
        ];
    }
}
