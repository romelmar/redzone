<?php
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();
use Illuminate\Support\Facades\DB;

$conflicts = DB::select("SELECT s.id AS current_id, s.account_number, CAST(s.account_number AS UNSIGNED) AS normalized, o.id AS existing_id, o.account_number AS existing_account FROM subscribers s JOIN subscribers o ON o.id != s.id AND o.id = CAST(s.account_number AS UNSIGNED)");
print_r($conflicts);
