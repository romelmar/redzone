<?php
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();
use Illuminate\Support\Facades\DB;

$rowsToMove = DB::table('subscribers')
    ->where('id', '>=', 10000000)
    ->orderBy('id')
    ->get(['id', 'account_number']);

$existingIds = DB::table('subscribers')
    ->orderBy('id')
    ->pluck('id')
    ->toArray();

$usedIds = array_flip($existingIds);
$nextId = 1;
$newIds = [];

foreach ($rowsToMove as $row) {
    while (isset($usedIds[$nextId])) {
        $nextId++;
    }
    $newIds[$row->id] = $nextId;
    $usedIds[$nextId] = true;
    $nextId++;
}

echo "old_id\tnew_id\taccount_number\n";
foreach ($newIds as $oldId => $newId) {
    echo $oldId . "\t" . $newId . "\t" . $rowsToMove->firstWhere('id', $oldId)->account_number . "\n";
}

echo 'count=' . count($newIds) . "\n";
