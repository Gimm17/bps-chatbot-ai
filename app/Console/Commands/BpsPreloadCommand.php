<?php

namespace App\Console\Commands;

use App\Bps\BpsApiClient;
use Illuminate\Console\Command;

/** Preload katalog discovery utama ke cache BPS 24 jam. */
class BpsPreloadCommand extends Command
{
    protected $signature = 'bps:preload';

    protected $description = 'Preload BPS domains, indicators, and variables into cache';

    public function handle(BpsApiClient $client): int
    {
        if ((string) config('bps.key', '') === '') {
            $this->error('BPS_WEBAPI_KEY is not configured.');

            return self::FAILURE;
        }

        $requests = [
            ['Domains', '/domain/model/domain', ['type' => 'all']],
            ['National indicators', '/list/model/indicators', ['domain' => '0000']],
            ['National variables', '/list/model/var', ['domain' => '0000']],
            ['Jawa Barat indicators', '/list/model/indicators', ['domain' => '3200']],
            ['Jawa Barat variables', '/list/model/var', ['domain' => '3200']],
        ];

        foreach ($requests as [$label, $path, $params]) {
            $this->line("Preloading {$label}...");
            try {
                $response = $client->get($path, $params);
            } catch (\Throwable $e) {
                $this->error("{$label}: request failed (".$e::class.').');

                return self::FAILURE;
            }

            if (! $response->isOk) {
                $this->error("{$label}: ".($response->errorMessage ?? 'BPS API error'));

                return self::FAILURE;
            }

            $this->line("  cached {$response->total} rows");
        }

        $this->info('Preload complete.');

        return self::SUCCESS;
    }
}
