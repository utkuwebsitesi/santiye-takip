<?php

return [
    'name' => env('APP_NAME', 'Şantiye Takip'),
    'env' => env('APP_ENV', 'production'),
    'debug' => (bool) env('APP_DEBUG', false),
    'url' => env('APP_URL', 'http://localhost'),
    'version' => env('APP_VERSION', '1.1.0'),
    'release_date' => env('APP_RELEASE_DATE', '25 Ağustos 2026'),
    'timezone' => env('APP_TIMEZONE', 'Europe/Istanbul'),
    'locale' => env('APP_LOCALE', 'tr'),
    'fallback_locale' => env('APP_FALLBACK_LOCALE', 'tr'),
    'cipher' => 'AES-256-CBC',
    'key' => env('APP_KEY'),
    'previous_keys' => [...array_filter(explode(',', env('APP_PREVIOUS_KEYS', '')))],
];
