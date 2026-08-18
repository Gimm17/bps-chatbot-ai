<?php

use App\Http\Middleware\LimitPayloadSize;
use App\Providers\AiServiceProvider;
use App\Providers\RagServiceProvider;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))

    ->booted(function (Application $app) {
        // Windows/XAMPP: arahkan cURL & OpenSSL ke CA bundle lokal agar
        // outbound HTTPS ke LimitRouter tidak gagal (cURL error 60).
        $ca = (string) env('CURL_CA_BUNDLE', '');
        if ($ca !== '' && file_exists($app->basePath($ca))) {
            $abs = $app->basePath($ca);
            @ini_set('curl.cainfo', $abs);
            @ini_set('openssl.cafile', $abs);
            putenv("CURL_CA_BUNDLE={$abs}");
            $_ENV['CURL_CA_BUNDLE'] = $abs;
        }
    })

    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Batasi ukuran body untuk semua request API (payload limit).
        $middleware->prepend(LimitPayloadSize::class);
    })
    ->withProviders([
        AiServiceProvider::class,
        RagServiceProvider::class,
    ])
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
