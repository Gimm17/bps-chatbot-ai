<?php

namespace App\Bps\Tools;

use App\Bps\BpsApiClient;
use App\Bps\BpsApiException;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

final class GetDynamicDataTool implements Tool
{
    public function __construct(private readonly BpsApiClient $client) {}

    public function description(): string
    {
        return 'Ambil nilai data dinamis BPS setelah domain, var id, dan period id (th) diketahui. Pertahankan kamus label resmi dan key komposit mentah.';
    }

    public function handle(Request $request): string
    {
        $arguments = $request->all();
        $domain = (string) ($arguments['domain'] ?? '');
        $var = (string) ($arguments['var'] ?? '');
        $th = (string) ($arguments['th'] ?? '');
        $params = ['domain' => $domain, 'var' => $var, 'th' => $th];
        foreach (['vervar', 'turvar', 'turth'] as $key) {
            $value = $arguments[$key] ?? null;
            if ($value !== null && $value !== '') {
                $params[$key] = (string) $value;
            }
        }

        try {
            $resp = $this->client->get('/list/model/data', $params);
        } catch (BpsApiException $e) {
            return $this->err($e->getMessage());
        }
        if (! $resp->isOk) {
            return $this->err($resp->errorMessage ?? 'BPS API error');
        }

        $raw = $resp->raw;
        $values = [];
        foreach ((array) ($raw['datacontent'] ?? []) as $key => $value) {
            $values[] = ['key' => (string) $key, 'value' => $value];
        }

        return (string) json_encode([
            'status' => 'ok',
            'domain' => $domain,
            'var_id' => $var,
            'period_id' => $th,
            'last_update' => $raw['last_update'] ?? null,
            'variable' => (array) ($raw['var'] ?? []),
            'vertical_variables' => (array) ($raw['vervar'] ?? []),
            'derived_variables' => (array) ($raw['turvar'] ?? []),
            'periods' => (array) ($raw['tahun'] ?? []),
            'derived_periods' => (array) ($raw['turtahun'] ?? []),
            'values' => $values,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'domain' => $schema->string()->required()->description('Domain id BPS'),
            'var' => $schema->string()->required()->description('Variable id dari ListVarsTool'),
            'th' => $schema->string()->required()->description('Period id dari daftar periode variabel'),
            'vervar' => $schema->string()->description('Filter vertical variable id'),
            'turvar' => $schema->string()->description('Filter derived variable id'),
            'turth' => $schema->string()->description('Filter derived period id'),
        ];
    }

    private function err(string $m): string
    {
        return (string) json_encode(['status' => 'error', 'message' => $m], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
