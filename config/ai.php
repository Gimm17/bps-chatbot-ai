<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default AI Provider Names
    |--------------------------------------------------------------------------
    */

    'default' => env('AI_DEFAULT_PROVIDER', 'limitrouter'),
    'default_for_images' => env('AI_DEFAULT_IMAGE_PROVIDER', 'limitrouter'),
    'default_for_audio' => env('AI_DEFAULT_AUDIO_PROVIDER', 'limitrouter'),
    'default_for_transcription' => env('AI_DEFAULT_TRANSCRIPTION_PROVIDER', 'limitrouter'),
    'default_for_embeddings' => env('AI_DEFAULT_EMBEDDING_PROVIDER', 'limitrouter'),
    'default_for_reranking' => env('AI_DEFAULT_RERANKING_PROVIDER', 'limitrouter'),

    'caching' => [
        'embeddings' => [
            'cache' => false,
            'store' => env('CACHE_STORE', 'database'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | AI Providers
    |--------------------------------------------------------------------------
    |
    | BPS AI Assistant uses a single server-side provider (LimitRouter) via the
    | openai-compatible driver. The browser NEVER calls LimitRouter directly —
    | every request flows through our internal API, which resolves this provider
    | server-side. Provider schema is never exposed to the UI.
    */

    'providers' => [
        'limitrouter' => [
            'driver' => 'openai-compatible',
            'url' => env('LIMITROUTER_BASE_URL', 'https://limitrouter.com/v1'),
            'key' => env('LIMITROUTER_API_KEY'),
            'models' => [
                'text' => [
                    'default' => env('LIMITROUTER_DEFAULT_MODEL', 'gpt-4o-mini'),
                ],
            ],
        ],
    ],

    // Demo application config (not provider secrets).
    'app' => [
        'demo_mode' => (bool) env('AI_DEMO_MODE', true),
        'default_model' => env('LIMITROUTER_DEFAULT_MODEL', 'gpt-4o-mini'),
        'timeout' => (int) env('AI_TIMEOUT', 30),
    ],

];
