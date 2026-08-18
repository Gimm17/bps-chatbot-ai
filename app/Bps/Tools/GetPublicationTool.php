<?php

namespace App\Bps\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

final class GetPublicationTool extends AbstractBpsTool implements Tool
{
    public function description(): string
    {
        return 'Ambil detail publikasi resmi BPS beserta abstract, tanggal rilis, dan URL PDF untuk citation.';
    }

    public function handle(Request $request): string
    {
        $arguments = $request->all();

        return $this->detail('/view/model/publication', [
            'domain' => (string) ($arguments['domain'] ?? ''),
            'lang' => (string) ($arguments['lang'] ?? 'ind'),
            'id' => (string) ($arguments['id'] ?? ''),
        ], 'publication');
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'domain' => $schema->string()->required()->description('Domain id BPS'),
            'id' => $schema->string()->required()->description('Publication id'),
            'lang' => $schema->string()->enum(['ind', 'eng'])->description('Bahasa'),
        ];
    }
}
