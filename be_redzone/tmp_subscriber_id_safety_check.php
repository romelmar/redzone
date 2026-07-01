<?php
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();
use Illuminate\Support\Facades\DB;

$total = DB::table('subscribers')->count();
$nonNumeric = DB::select("SELECT COUNT(*) AS cnt FROM subscribers WHERE account_number NOT REGEXP '^[0-9]+$'")[0]->cnt;
$leadingZeroCount = DB::table('subscribers')->where('account_number', 'like', '0%')->count();
$distinctAccountNumbers = DB::table('subscribers')->distinct('account_number')->count('account_number');
$distinctIntAccountNumbers = DB::table('subscribers')->selectRaw('COUNT(DISTINCT CAST(account_number AS UNSIGNED)) AS cnt')->value('cnt');
$duplicateIntCount = DB::select("SELECT COUNT(*) AS cnt FROM (SELECT CAST(account_number AS UNSIGNED) AS normalized, COUNT(*) AS cnt FROM subscribers GROUP BY normalized HAVING COUNT(*) > 1) t")[0]->cnt;
$conflictIds = DB::select("SELECT COUNT(*) AS cnt FROM subscribers s JOIN subscribers o ON o.id != s.id AND o.id = CAST(s.account_number AS UNSIGNED)")[0]->cnt;
$subscriberIdRefs = DB::select("SELECT TABLE_NAME, COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND COLUMN_NAME = 'subscriber_id'");
print_r(["total" => $total, "non_numeric" => $nonNumeric, "leading_zero" => $leadingZeroCount, "distinct_account_number" => $distinctAccountNumbers, "distinct_int_account_number" => $distinctIntAccountNumbers, "duplicate_int_normalized_groups" => $duplicateIntCount, "id_conflicts" => $conflictIds]);
print_r($subscriberIdRefs);
