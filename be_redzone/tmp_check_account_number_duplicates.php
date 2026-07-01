<?php
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();
use Illuminate\Support\Facades\DB;

$dup = DB::select("SELECT COUNT(*) AS cnt FROM (SELECT CAST(account_number AS UNSIGNED) AS normalized FROM subscribers GROUP BY normalized HAVING COUNT(*) > 1) AS t");
$nonNumeric = DB::select("SELECT COUNT(*) AS cnt FROM subscribers WHERE account_number NOT REGEXP '^[0-9]+$'");
$max=DB::select("SELECT MAX(CAST(account_number AS UNSIGNED)) AS max FROM subscribers");
$min=DB::select("SELECT MIN(CAST(account_number AS UNSIGNED)) AS min FROM subscribers");
$distinct=DB::select("SELECT COUNT(DISTINCT CAST(account_number AS UNSIGNED)) AS cnt FROM subscribers");
$total=DB::select("SELECT COUNT(*) AS cnt FROM subscribers");

echo 'dup=' . $dup[0]->cnt . "\n";
echo 'nonNumeric=' . $nonNumeric[0]->cnt . "\n";
echo 'max=' . $max[0]->max . "\n";
echo 'min=' . $min[0]->min . "\n";
echo 'distinct=' . $distinct[0]->cnt . "\n";
echo 'total=' . $total[0]->cnt . "\n";
