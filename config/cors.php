<?php

/*
 * CORS only matters for browser clients (the Expo web build); native apps ignore it.
 * Any origin may call the API: every endpoint except login/register still needs a bearer token,
 * and no cookies are used, so an open origin list adds no cross-site risk. Set
 * CORS_ALLOWED_ORIGINS to a comma-separated list to restrict it.
 */
return [
    'paths' => ['api/*'],

    'allowed_methods' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],

    'allowed_origins' => array_filter(array_map(
        'trim',
        explode(',', env('CORS_ALLOWED_ORIGINS', '*'))
    )),

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['Content-Type', 'Authorization', 'Accept', 'X-Requested-With'],

    'exposed_headers' => [],

    'max_age' => 600,

    // Auth uses bearer tokens, not cookies.
    'supports_credentials' => false,
];
