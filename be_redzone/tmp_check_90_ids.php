<?php
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();
use Illuminate\Support\Facades\DB;

$rows = DB::select(
    "SELECT id, account_number FROM subscribers WHERE account_number LIKE '90%' OR id BETWEEN 90000 AND 90999 ORDER BY id LIMIT 200"
);

echo "id\taccount_number\n";
foreach ($rows as $row) {
    echo $row->id . "\t" . $row->account_number . "\n";
}

echo 'count=' . count($rows) . "\n";
