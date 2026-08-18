<?php

namespace App\Bps\Tools;

use App\Bps\BpsApiClient;
use App\Bps\BpsApiException;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

final class ListVarsTool implements Tool
{
    public function __construct(private readonly BpsApiClient $client) {}

    public function description(): string
    {
        return 'List variabel statistik BPS untuk memperoleh var id. Pakai saat var id belum diketahui; domain wajib.';
    }

    public function handle(Request $request): string
    {
        $arguments = $request->all();
        $params = ['domain' => (string) ($arguments['domain'] ?? '')];
        foreach (['subject', 'lang', 'year', 'page'] as $key) {
            $value = $arguments[$key] ?? null;
            if ($value !== null && $value !== '') {
                $params[$key] = (string) $value;
            }
        }

        try {
            $resp = $this->client->get('/list/model/var', $params);
        } catch (BpsApiException $e) {
            return $this->err($e->getMessage());
        }
        if (! $resp->isOk) {
            return $this->err($resp->errorMessage ?? 'BPS API error');
        }

        $rows = array_map(fn ($r) => [
            'var_id' => $r['var_id'] ?? null,
            'title' => $r['title'] ?? null,
            'unit' => $r['unit'] ?? null,
            'sub_id' => $r['sub_id'] ?? null,
            'sub_name' => $r['sub_name'] ?? null,
            'def' => $r['def'] ?? null,
            'notes' => $r['notes'] ?? null,
        ], $resp->rows);

        return (string) json_encode(['status' => 'ok', 'total' => $resp->total, 'variables' => $rows], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'domain' => $schema->string()->required()->description('Domain id BPS; gunakan ListDomainsTool bila belum diketahui'),
            'subject' => $schema->string()->description('Subject id opsional'),
            'lang' => $schema->string()->description('Bahasa opsional'),
            'year' => $schema->string()->description('Tahun opsional'),
            'page' => $schema->integer()->description('Halaman opsional'),
        ];
    }

    private function err(string $m): string
    {
        return (string) json_encode(['status' => 'error', 'message' => $m], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
