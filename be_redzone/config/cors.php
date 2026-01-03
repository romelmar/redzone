<?php

return [
    'paths' => ['api/*', 'sanctum/csrf-cookie', 'subscriptions/*'],
    'allowed_methods' => ['*'],
    'allowed_origins' => ['https://redzone.rosnel-partnership.com'], // or ['*'] for testing
    'allowed_origins_patterns' => [],
    'allowed_headers' => ['*'],
    'exposed_headers' => [],
    'max_age' => 0,
    'supports_credentials' => true,
];

