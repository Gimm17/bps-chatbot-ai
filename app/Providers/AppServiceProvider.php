<?php

namespace App\Providers;

use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Windows/XAMPP: Guzzle default verify=true gagal (cURL error 60) karena
        // tidak ada CA bundle sistem. Arahkan ke cacert.pem lokal bila ada.
        $abs = self::resolveCaPath($this->app, (string) env('CURL_CA_BUNDLE', ''));
        if ($abs !== null) {
            Http::globalOptions(['verify' => $abs]);
        }
    }

    public static function resolveCaPath(Application $app, string $ca): ?string
    {
        if ($ca === '') {
            return null;
        }

        return realpath($ca) ?: (realpath($app->basePath($ca)) ?: null);
    }
}
