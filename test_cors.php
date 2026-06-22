<?php
require __DIR__.'/be_redzone/vendor/autoload.php';
use Fruitcake\Cors\CorsService;
use Symfony\Component\HttpFoundation\Request;

$config = require __DIR__.'/be_redzone/config/cors.php';
$service = new CorsService($config);
$request = Request::create('/sanctum/csrf-cookie', 'OPTIONS', [], [], [], [
    'HTTP_ORIGIN' => 'http://localhost:5173',
    'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => 'GET',
    'HTTP_ACCESS_CONTROL_REQUEST_HEADERS' => 'Content-Type',
]);
$response = $service->handlePreflightRequest($request);
foreach ($response->headers->allPreserveCase() as $name => $values) {
    foreach ($values as $value) {
        echo "$name: $value\n";
    }
}
