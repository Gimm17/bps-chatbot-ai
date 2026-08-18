<?php

namespace App\Bps\Tools;

use App\Bps\BpsApiClient;
use App\Bps\BpsApiException;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

/**
 * Endpoint ini didokumentasikan BPS, tetapi ketersediaan live dapat berubah;
 * pemanggil harus mengizinkan fallback definisi dari berkas .md.
 */
final class GetGlosariumTool implements Tool
{
    public function __construct(private readonly BpsApiClient $client) {}

    public function description(): string
    {
        return 'Cari atau ambil detail glosarium BPS untuk definisi istilah statistik. Gunakan id untuk detail; tanpa id untuk daftar.';
    }

    public function handle(Request $request): string
    {
        $arguments = $request->all();
        $id = $arguments['id'] ?? null;
        $params = $id
            ? ['id' => (string) $id, 'lang' => $arguments['lang'] ?? null]
            : [
                'prefix' => $arguments['prefix'] ?? null,
                'page' => $arguments['page'] ?? null,
                'perpage' => $arguments['perpage'] ?? null,
            ];

        try {
            $resp = $this->client->get($id ? '/view/model/glosarium' : '/list/model/glosarium', $params);
        } catch (BpsApiException $e) {
            return $this->err($e->getMessage());
        }
        if (! $resp->isOk) {
            return $this->err($resp->errorMessage ?? 'BPS API error');
        }

        $rows = array_map(function (array $row): array {
            $id = $row['id'] ?? $row['glosarium_id'] ?? null;
            $term = $row['term'] ?? $row['istilah'] ?? $row['title'] ?? null;
            $definition = $row['definition'] ?? $row['def_text'] ?? $row['def'] ?? null;
            if ($id === null && $term === null && $definition === null) {
                return ['raw' => $row];
            }

            return ['id' => $id, 'term' => $term, 'definition' => $definition];
        }, $resp->rows);

        return (string) json_encode(['status' => 'ok', 'total' => $resp->total, 'terms' => $rows], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->string()->description('ID glosarium untuk detail'),
            'lang' => $schema->string()->description('Bahasa detail, misalnya ind atau eng'),
            'prefix' => $schema->string()->description('Awalan istilah untuk daftar'),
            'page' => $schema->integer()->description('Halaman daftar'),
            'perpage' => $schema->integer()->description('Jumlah hasil per halaman'),
        ];
    }

    private function err(string $m): string
    {
        return (string) json_encode(['status' => 'error', 'message' => $m], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
