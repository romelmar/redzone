<?php
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();
use Illuminate\Support\Facades\DB;
$fallback = DB::select('select count(*) as cnt from subscribers where id >= 100000000');
$orphans = DB::select('select count(*) as cnt from subscriptions where subscriber_id not in (select id from subscribers)');
echo "fallback={$fallback[0]->cnt} orphans={$orphans[0]->cnt}\n";
