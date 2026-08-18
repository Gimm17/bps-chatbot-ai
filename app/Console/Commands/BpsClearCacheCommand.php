<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class BpsClearCacheCommand extends Command
{
    protected $signature = 'bps:clear-cache';

    protected $description = 'Clear the dedicated application cache store used by BPS WebAPI';

    public function handle(): int
    {
        Cache::flush();
        $this->info('BPS dedicated cache store cleared.');

        return self::SUCCESS;
    }
}
