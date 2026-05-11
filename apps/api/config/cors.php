<?php

declare(strict_types=1);

return [
    'paths' => ['api/*', 'up'],

    'allowed_methods' => ['*'],

    'allowed_origins' => [
        'https://cl-fixture-api.vercel.app',
        'http://localhost:4200',
    ],

    'allowed_origins_patterns' => [
        '#^https://cl-fixture-api-[a-z0-9-]+\.vercel\.app$#',
    ],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => false,
];
