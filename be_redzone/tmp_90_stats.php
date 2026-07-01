<?php
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();
use Illuminate\Support\Facades\DB;

$count = DB::table('subscribers')->where('account_number', 'like', '90%')->count();
$distinct = DB::table('subscribers')->where('account_number', 'like', '90%')->distinct('account_number')->count('account_number');
$sample = DB::select("SELECT account_number, TRIM(LEADING '0' FROM SUBSTR(account_number, 2)) AS normalized FROM subscribers WHERE account_number LIKE '90%' ORDER BY account_number LIMIT 100");
$collision = DB::select(
    "SELECT s.account_number AS original, s.normalized, existing.account_number AS existing\n" .
    "FROM (SELECT account_number, TRIM(LEADING '0' FROM SUBSTR(account_number, 2)) AS normalized FROM subscribers WHERE account_number LIKE '90%') AS s\n" .
    "JOIN subscribers existing ON existing.account_number = s.normalized AND existing.account_number NOT LIKE '90%'\n" .
    "LIMIT 20"
);

echo "count_90_prefix=$count\n";
echo "distinct_90_prefix=$distinct\n";
echo "sample=\n";
foreach ($sample as $row) {
    echo "{$row->account_number}\t{$row->normalized}\n";
}

echo "collisions=\n";
foreach ($collision as $row) {
    echo "{$row->original}\t{$row->normalized}\t{$row->existing}\n";
}
