<?php
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();
use Illuminate\Support\Facades\DB;
$fallbackCount = DB::select('select count(*) as cnt from subscribers where id >= 100000000')[0]->cnt;
$minUnused = DB::select("select min(t.id + 1) as next_free from (select id from subscribers order by id) t left join (select id + 1 as nxt from subscribers) n on t.id + 1 = n.nxt where n.nxt is null");
$nextFree = $minUnused[0]->next_free;
$sample = DB::select('select id, account_number from subscribers where id >= 100000000 order by id limit 20');
echo "fallbackCount={$fallbackCount}\n";
echo "nextFree={$nextFree}\n";
echo "sample=\n";
foreach ($sample as $row) {
    echo $row->id . '\t' . $row->account_number . "\n";
}
$orphanCount = DB::select('select count(*) as cnt from subscriptions where subscriber_id not in (select id from subscribers)')[0]->cnt;
echo "orphans={$orphanCount}\n";
