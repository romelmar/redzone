<?php
require __DIR__.'/be_redzone/vendor/autoload.php';
$app = require __DIR__.'/be_redzone/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();
$cfg = config('cors');
echo json_encode($cfg, JSON_PRETTY_PRINT);
