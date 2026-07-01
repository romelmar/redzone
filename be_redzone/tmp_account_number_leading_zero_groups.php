<?php
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();
use Illuminate\Support\Facades\DB;

$groups = DB::select("SELECT normalized, GROUP_CONCAT(CONCAT(id, ':', account_number) ORDER BY account_number SEPARATOR ', ') AS group_values FROM (SELECT id, account_number, TRIM(LEADING '0' FROM account_number) AS normalized FROM subscribers) t GROUP BY normalized HAVING COUNT(*) > 1 ORDER BY normalized");
foreach ($groups as $group) {
    echo "normalized={$group->normalized} => {$group->group_values}\n";
}
