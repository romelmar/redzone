<?php
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();
use Illuminate\Support\Facades\DB;

$conflicts = DB::select(
    "SELECT s.id AS id, s.account_number AS account_number, t.normalized AS normalized\n"
    . "FROM subscribers s\n"
    . "JOIN (\n"
    . "  SELECT TRIM(LEADING '0' FROM SUBSTR(account_number, 2)) AS normalized\n"
    . "  FROM subscribers\n"
    . "  WHERE account_number REGEXP '^9' AND CAST(account_number AS UNSIGNED) > 9000\n"
    . ") AS t ON s.account_number = t.normalized\n"
    . "WHERE NOT (s.account_number REGEXP '^9' AND CAST(s.account_number AS UNSIGNED) > 9000)\n"
    . "ORDER BY normalized, s.account_number\n"
);

echo "id\taccount_number\tnormalized\n";
foreach ($conflicts as $c) {
    echo $c->id . "\t" . $c->account_number . "\t" . $c->normalized . "\n";
}
