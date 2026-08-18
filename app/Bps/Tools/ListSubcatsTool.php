<?php

namespace App\Bps\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

final class ListSubcatsTool extends AbstractBpsTool implements Tool
{
    public function description(): string
    {
        return 'Daftar subkategori statistik BPS dalam domain untuk mempersempit katalog data.';
    }

    public function handle(Request $request): string
    {
        $arguments = $request->all();

        return $this->list('/list/model/subcat', [
            'domain' => (string) ($arguments['domain'] ?? ''),
            ...$this->optional($arguments, ['lang']),
        ], 'subcategories');
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'domain' => $schema->string()->required()->description('Domain id BPS'),
            'lang' => $schema->string()->enum(['ind', 'eng'])->description('Bahasa'),
        ];
    }
}
