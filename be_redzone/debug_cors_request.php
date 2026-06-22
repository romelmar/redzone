<?php
require __DIR__.'/vendor/autoload.php';
$app = require __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$request = Illuminate\Http\Request::create('/sanctum/csrf-cookie', 'OPTIONS', [], [], [], [
    'HTTP_ORIGIN' => 'http://localhost:5173',
    'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => 'GET',
    'HTTP_ACCESS_CONTROL_REQUEST_HEADERS' => 'content-type',
]);
$response = $kernel->handle($request);
echo $response->getStatusCode() . "\n";
foreach ($response->headers->allPreserveCase() as $name => $values) {
    foreach ($values as $value) {
        echo "$name: $value\n";
    }
}
$kernel->terminate($request, $response);
