<?php
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();
use Illuminate\Support\Facades\DB;

$total90 = DB::table('subscribers')->where('account_number', 'like', '90%')->count();
$collisions = DB::select(
    "SELECT COUNT(*) AS cnt FROM (\n" .
    "  SELECT TRIM(LEADING '0' FROM SUBSTR(account_number, 2)) AS normalized\n" .
    "  FROM subscribers WHERE account_number LIKE '90%'\n" .
    ") AS t\n" .
    "JOIN subscribers s ON s.account_number = t.normalized AND s.account_number NOT LIKE '90%'"
);
$safe = DB::select(
    "SELECT COUNT(*) AS cnt FROM (\n" .
    "  SELECT account_number, TRIM(LEADING '0' FROM SUBSTR(account_number, 2)) AS normalized\n" .
    "  FROM subscribers WHERE account_number LIKE '90%'\n" .
    ") AS t\n" .
    "LEFT JOIN subscribers s ON s.account_number = t.normalized AND s.account_number NOT LIKE '90%'\n" .
    "WHERE s.account_number IS NULL"
);

echo "total90=$total90\n";
echo "collision_count={$collisions[0]->cnt}\n";
echo "safe_count={$safe[0]->cnt}\n";
