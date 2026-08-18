<?php

namespace App\Bps\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

final class ListUnitsTool extends AbstractBpsTool implements Tool
{
    public function description(): string
    {
        return 'Daftar satuan resmi BPS dalam domain untuk menjelaskan nilai statistik dengan benar.';
    }

    public function handle(Request $request): string
    {
        $arguments = $request->all();

        return $this->list('/list/model/unit', ['domain' => (string) ($arguments['domain'] ?? '')], 'units');
    }

    public function schema(JsonSchema $schema): array
    {
        return ['domain' => $schema->string()->required()->description('Domain id BPS')];
    }
}
