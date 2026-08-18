<?php

namespace App\Providers;

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
        $ca = (string) env('CURL_CA_BUNDLE', '');
        $abs = $this->app->basePath($ca);
        if ($ca !== '' && file_exists($abs)) {
            Http::globalOptions(['verify' => $abs]);
        }
    }
}
