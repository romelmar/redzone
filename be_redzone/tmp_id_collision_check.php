<?php
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();
use Illuminate\Support\Facades\DB;

$total = DB::select("SELECT COUNT(*) AS total, COUNT(DISTINCT TRIM(LEADING '0' FROM account_number)) AS distinct_normalized FROM subscribers");
$duplicates = DB::select("SELECT normalized, COUNT(*) AS cnt FROM (SELECT TRIM(LEADING '0' FROM account_number) AS normalized FROM subscribers) t GROUP BY normalized HAVING cnt > 1 ORDER BY cnt DESC LIMIT 100");
$conflicts = DB::select("SELECT s.id, s.account_number, TRIM(LEADING '0' FROM s.account_number) AS normalized FROM subscribers s JOIN subscribers o ON o.id != s.id AND o.id = TRIM(LEADING '0' FROM s.account_number) ORDER BY normalized LIMIT 100");
print_r($total[0]);
print_r($duplicates);
print_r($conflicts);
