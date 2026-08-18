<?php

namespace App\Bps\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

final class ListSubjectsTool extends AbstractBpsTool implements Tool
{
    public function description(): string
    {
        return 'Daftar subjek statistik BPS dalam domain. Gunakan untuk menemukan subject id sebelum mencari variabel.';
    }

    public function handle(Request $request): string
    {
        $arguments = $request->all();

        return $this->list('/list/model/subject', [
            'domain' => (string) ($arguments['domain'] ?? ''),
            ...$this->optional($arguments, ['lang']),
        ], 'subjects');
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'domain' => $schema->string()->required()->description('Domain id BPS'),
            'lang' => $schema->string()->enum(['ind', 'eng'])->description('Bahasa'),
        ];
    }
}
