<?php
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();
use Illuminate\Support\Facades\DB;
$total = DB::select("SELECT COUNT(*) AS total, COUNT(DISTINCT TRIM(LEADING '0' FROM account_number)) AS distinct_normalized FROM subscribers");
$dupCount = DB::select("SELECT COUNT(*) AS cnt FROM (SELECT TRIM(LEADING '0' FROM account_number) AS normalized FROM subscribers GROUP BY normalized HAVING COUNT(*) > 1) t");
$conflictCount = DB::select("SELECT COUNT(*) AS cnt FROM subscribers s JOIN subscribers o ON o.id != s.id AND o.id = TRIM(LEADING '0' FROM s.account_number)");
$example = DB::select("SELECT s.id, s.account_number, TRIM(LEADING '0' FROM s.account_number) AS normalized FROM subscribers s JOIN subscribers o ON o.id != s.id AND o.id = TRIM(LEADING '0' FROM s.account_number) LIMIT 20");
print_r($total[0]);
print_r($dupCount[0]);
print_r($conflictCount[0]);
print_r($example);
