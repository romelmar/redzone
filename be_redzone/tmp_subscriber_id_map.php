<?php
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();
use Illuminate\Support\Facades\DB;

$counts = DB::select("SELECT 
    SUM(id BETWEEN 1 AND 999) AS id_1_999,
    SUM(id BETWEEN 90000 AND 90999) AS id_90000_90999,
    SUM(account_number LIKE '90%') AS account_90_prefix
FROM subscribers");
$rows = DB::select("SELECT id, account_number FROM subscribers WHERE id BETWEEN 1 AND 200 ORDER BY id");
$rows2 = DB::select("SELECT id, account_number FROM subscribers WHERE id BETWEEN 90000 AND 90250 ORDER BY id");

echo 'counts:\n';
foreach ($counts as $c) {
    echo "id_1_999={$c->id_1_999}\n";
    echo "id_90000_90999={$c->id_90000_90999}\n";
    echo "account_90_prefix={$c->account_90_prefix}\n";
}
echo '\nrows id 1-200:\n';
foreach ($rows as $r) {
    echo "{$r->id}\t{$r->account_number}\n";
}
echo '\nrows id 90000-90250:\n';
foreach ($rows2 as $r) {
    echo "{$r->id}\t{$r->account_number}\n";
}
