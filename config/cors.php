<?php

/*
 * CORS only matters for browser clients (the Expo web build); native apps ignore it.
 * Set CORS_ALLOWED_ORIGINS to a comma-separated list for deployed web builds.
 */
return [
    'paths' => ['api/*'],

    'allowed_methods' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],

    'allowed_origins' => array_filter(array_map(
        'trim',
        explode(',', env('CORS_ALLOWED_ORIGINS', 'http://localhost:8081,http://localhost:19006'))
    )),

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['Content-Type', 'Authorization', 'Accept', 'X-Requested-With'],

    'exposed_headers' => [],

    'max_age' => 600,

    // Auth uses bearer tokens, not cookies.
    'supports_credentials' => false,
];
