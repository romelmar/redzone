<?php

return [
    'enabled' => env('PAYMONGO_ENABLED', false),
    'mode' => env('PAYMONGO_MODE', 'test'),
    'secret_key' => env('PAYMONGO_SECRET_KEY'),
    'webhook_secret' => env('PAYMONGO_WEBHOOK_SECRET'),
    'public_url' => env('PAYMONGO_PUBLIC_URL'),
];
