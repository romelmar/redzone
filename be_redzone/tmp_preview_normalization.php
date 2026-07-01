<?php
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();
use Illuminate\Support\Facades\DB;

$rows = DB::select(
    "SELECT id, account_number, CAST(account_number AS UNSIGNED) AS numeric_value, TRIM(LEADING '0' FROM SUBSTR(account_number, 2)) AS normalized"
    . " FROM subscribers"
    . " WHERE account_number REGEXP '^9' AND CAST(account_number AS UNSIGNED) > 9000"
    . " ORDER BY account_number"
);

echo "id\taccount_number\tnumeric_value\tnormalized\n";
foreach ($rows as $r) {
    echo $r->id . "\t" . $r->account_number . "\t" . $r->numeric_value . "\t" . $r->normalized . "\n";
}
