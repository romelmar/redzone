<?php
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();
use Illuminate\Support\Facades\DB;

$rows = DB::select(
    "SELECT normalized, GROUP_CONCAT(account_number ORDER BY account_number SEPARATOR ',') AS examples, COUNT(*) AS cnt \n"
    . "FROM (SELECT account_number, TRIM(LEADING '0' FROM SUBSTR(account_number, 2)) AS normalized FROM subscribers WHERE account_number REGEXP '^9') AS t \n"
    . "GROUP BY normalized HAVING COUNT(*) > 1"
);

foreach ($rows as $row) {
    echo $row->normalized . ' cnt=' . $row->cnt . ' examples=' . $row->examples . "\n";
}
