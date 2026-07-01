<?php
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();
use Illuminate\Support\Facades\DB;

$rows = DB::select("SELECT id, account_number, CONCAT('9', account_number) AS transformed FROM subscribers WHERE account_number LIKE '0%'");
$targets = array_column($rows, 'transformed');
$placeholders = implode(',', array_fill(0, count($targets), '?'));
$conflicts = [];
if ($targets) {
    $conflicts = DB::select("SELECT id, account_number FROM subscribers WHERE account_number IN ($placeholders)", $targets);
}

echo 'count_starting_zero=' . count($rows) . "\n";
echo 'count_conflicts=' . count($conflicts) . "\n";
if ($conflicts) {
    echo "conflicts:\n";
    foreach ($conflicts as $c) {
        echo "{$c->id}:{$c->account_number}\n";
    }
}
foreach ($rows as $row) {
    echo "{$row->id}:{$row->account_number} => {$row->transformed}\n";
}
