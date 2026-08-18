<?php

namespace App\Bps\Tools;

use App\Bps\BpsApiClient;
use App\Bps\BpsApiException;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

final class ListDomainsTool implements Tool
{
    private const MAX_RESULTS = 100;

    public function __construct(private readonly BpsApiClient $client) {}

    public function description(): string
    {
        return 'List domain BPS (wilayah administratif) untuk dapat domain_id. '
            .'type: all|prov|kab|kabbyprov (kabbyprov butuh prov=4-digit). '
            .'Pakai bila domain_id belum diketahui. Jawa Barat=3200, Nasional=0000.';
    }

    public function handle(Request $request): string
    {
        $arguments = $request->all();
        $params = ['type' => (string) ($arguments['type'] ?? 'all')];
        $prov = $arguments['prov'] ?? null;
        if ($prov !== null && $prov !== '') {
            $params['prov'] = (string) $prov;
        }

        try {
            $resp = $this->client->get('/domain/model/domain', $params);
        } catch (BpsApiException $e) {
            return $this->err($e->getMessage());
        }
        if (! $resp->isOk) {
            return $this->err($resp->errorMessage ?? 'BPS API error');
        }

        $rows = array_map(fn ($r) => [
            'domain_id' => $r['domain_id'] ?? null,
            'domain_name' => $r['domain_name'] ?? null,
            'domain_url' => $r['domain_url'] ?? null,
        ], array_slice($resp->rows, 0, self::MAX_RESULTS));
        $returned = count($rows);

        return (string) json_encode([
            'status' => 'ok',
            'total' => $resp->total,
            'returned' => $returned,
            'truncated' => $resp->total > $returned,
            'domains' => $rows,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'type' => $schema->string()->enum(['all', 'prov', 'kab', 'kabbyprov'])->required()->description('Jenis domain BPS'),
            'prov' => $schema->string()->description('4-digit province id, wajib bila type=kabbyprov'),
        ];
    }

    private function err(string $m): string
    {
        return (string) json_encode(['status' => 'error', 'message' => $m], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
