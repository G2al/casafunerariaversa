<?php

return [
    'enabled' => (bool) env('OCCUPANCY_REALTIME_ENABLED', false),
    'host' => env('REVERB_PUBLIC_HOST', parse_url(env('APP_URL', 'http://localhost'), PHP_URL_HOST) ?: 'localhost'),
    'port' => (int) env('REVERB_PUBLIC_PORT', 443),
    'scheme' => env('REVERB_PUBLIC_SCHEME', 'https'),
    'path' => '/realtime',
];
