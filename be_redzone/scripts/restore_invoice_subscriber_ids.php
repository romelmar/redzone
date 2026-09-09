<?php

// CLI only. Restores the ten approved invoice IDs and relocates their current holders.
// Default rehearses on temporary tables. --apply changes only local be_redzone.
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require dirname(__DIR__).'/vendor/autoload.php';
$app = require dirname(__DIR__).'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

$apply = in_array('--apply', $argv, true);
$db = DB::connection();
if ($db->getDriverName() !== 'mysql' || $db->getDatabaseName() !== 'be_redzone'
    || !in_array($db->getConfig('host'), ['localhost', '127.0.0.1'], true)) {
    throw new RuntimeException('This operation is restricted to local be_redzone.');
}

$refs = $db->select("SELECT TABLE_SCHEMA, TABLE_NAME, COLUMN_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE REFERENCED_TABLE_SCHEMA = DATABASE() AND REFERENCED_TABLE_NAME = 'subscribers'");
if (count($refs) !== 1 || $refs[0]->TABLE_SCHEMA !== 'be_redzone'
    || $refs[0]->TABLE_NAME !== 'subscriptions' || $refs[0]->COLUMN_NAME !== 'subscriber_id') {
    throw new RuntimeException('Reference schema changed; review all references before running.');
}
$columns = $db->select("SELECT TABLE_NAME, COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND COLUMN_NAME LIKE '%subscriber%'");
if (count($columns) !== 1 || $columns[0]->TABLE_NAME !== 'subscriptions' || $columns[0]->COLUMN_NAME !== 'subscriber_id') {
    throw new RuntimeException('Unexpected subscriber reference column.');
}
if ($db->select("SELECT TRIGGER_NAME FROM information_schema.TRIGGERS WHERE TRIGGER_SCHEMA = DATABASE()")) {
    throw new RuntimeException('Triggers require manual review.');
}

$tables = ['subscribers', 'subscriptions', 'payments', 'addons', 'service_credits', 'subscription_rates', 'subscription_events', 'collection_assignments', 'plans'];
foreach (['payment_audits', 'collector_remittances'] as $optionalTable) {
    if ($db->getSchemaBuilder()->hasTable($optionalTable)) {
        $tables[] = $optionalTable;
    }
}
foreach ($tables as $table) {
    $engine = $db->selectOne('SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?', [$table]);
    if (($engine->ENGINE ?? '') !== 'InnoDB') {
        throw new RuntimeException('All affected tables must support transactions.');
    }
}

$maintenanceStarted = false;
$committed = false;
$backupDir = null;
$originalFkChecks = (int) $db->selectOne('SELECT @@SESSION.foreign_key_checks AS enabled')->enabled;
$snapshot = [];

try {
    if ($apply && !$app->isDownForMaintenance()) {
        Artisan::call('down', ['--retry' => 30]);
        $maintenanceStarted = true;
    }
    $db->statement('SET SESSION TRANSACTION ISOLATION LEVEL REPEATABLE READ');
    $db->beginTransaction();
    // Lock full ranges, including insert gaps, while capturing and changing the data.
    foreach ($tables as $table) {
        $query = $db->table($table)->orderBy('id');
        $snapshot[$table] = ($apply ? $query->lockForUpdate() : $query)->get()->map(fn ($row) => (array) $row)->all();
    }
    if ($db->table('jobs')->count() !== 0) {
        throw new RuntimeException('Pending jobs must be drained before IDs change.');
    }
    $approved = json_decode('[{"current": 967, "invoice": 236, "name": "Mary Queen Salmorin", "holder": "Caballero (Isian Norte)", "relocated": 1193}, {"current": 1012, "invoice": 353, "name": "Clarito, Jenelyn", "holder": "Calasagsag (Buntalan)", "relocated": 1194}, {"current": 1118, "invoice": 901, "name": "Krayziel Cain", "holder": "Caparanga", "relocated": 1195}, {"current": 749, "invoice": 758, "name": "Aldreyn Reymer Mortel", "holder": "Ma. Marilyn Tacadao", "relocated": 1196}, {"current": 963, "invoice": 279, "name": "Jolina A\u00f1onuevo", "holder": "Cabana, Sixto", "relocated": 1197}, {"current": 849, "invoice": 589, "name": "CreditAccess Philippines Financing Company Inc.", "holder": "Myla Calugdan", "relocated": 1198}, {"current": 848, "invoice": 858, "name": "Renalyn Cabusbusan", "holder": "Neta Relliza", "relocated": 1199}, {"current": 627, "invoice": 634, "name": "Rheden Vallejo", "holder": "Ruby Calagadmo", "relocated": 1200}, {"current": 834, "invoice": 844, "name": "Sharmie Vargas", "holder": "Hilapad Kirk", "relocated": 1201}, {"current": 867, "invoice": 141, "name": "Miac_Ac Elementary School", "holder": "Villaruz", "relocated": 1202}]', true, 512, JSON_THROW_ON_ERROR);
    $mapping = [];
    $names = [];
    foreach ($snapshot['subscribers'] as $row) {
        $mapping[(int) $row['id']] = (int) $row['id'];
        $names[(int) $row['id']] = $row['name'];
    }
    foreach ($approved as $change) {
        if (($names[$change['current']] ?? null) !== $change['name']
            || ($names[$change['invoice']] ?? null) !== $change['holder']
            || isset($names[$change['relocated']])) {
            throw new RuntimeException('Account identities or destination availability changed. No IDs changed.');
        }
        $mapping[$change['current']] = $change['invoice'];
        $mapping[$change['invoice']] = $change['relocated'];
    }
    if (count(array_unique(array_values($mapping))) !== count($mapping)) {
        throw new RuntimeException('Destination IDs are not unique.');
    }
    $changes = array_filter($mapping, fn ($new, $old) => $new !== $old, ARRAY_FILTER_USE_BOTH);
    $finalMaximum = max(array_values($mapping));
    foreach ($snapshot['subscriptions'] as $row) {
        if (!isset($mapping[(int) $row['subscriber_id']])) {
            throw new RuntimeException('An orphan subscription was found.');
        }
    }
    $maximum = $mapping ? max(array_keys($mapping)) : 0;
    $offset = $maximum + count($mapping) + 1;

    if ($apply) {
        // Store personal data outside the web root, with a reversible SQL snapshot.
        $backupDir = sys_get_temp_dir().'/redzone-invoice-id-restore-'.date('Ymd-His').'-'.bin2hex(random_bytes(4));
        if (!mkdir($backupDir, 0700, true)) {
            throw new RuntimeException('Cannot create backup directory.');
        }
        $json = json_encode($snapshot, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT);
        if (file_put_contents($backupDir.'/snapshot.json', $json, LOCK_EX) !== strlen($json)
            || hash_file('sha256', $backupDir.'/snapshot.json') !== hash('sha256', $json)) {
            throw new RuntimeException('Backup verification failed.');
        }
        $csv = "old_subscriber_id,new_subscriber_id\n";
        foreach ($mapping as $old => $new) {
            $csv .= "$old,$new\n";
        }
        if (file_put_contents($backupDir.'/id-map.csv', $csv, LOCK_EX) !== strlen($csv)) {
            throw new RuntimeException('Cannot write mapping.');
        }
        $sql = "-- Restore only with the app stopped, before accepting further writes.\nUSE `be_redzone`;\nSET FOREIGN_KEY_CHECKS=0;\nSTART TRANSACTION;\nDELETE FROM `subscriptions`;\nDELETE FROM `subscribers`;\n";
        foreach (['subscribers', 'subscriptions'] as $table) {
            foreach ($snapshot[$table] as $row) {
                $names = implode(',', array_map(fn ($name) => '`'.$name.'`', array_keys($row)));
                $values = implode(',', array_map(fn ($value) => $value === null ? 'NULL' : $db->getPdo()->quote((string) $value), array_values($row)));
                $sql .= "INSERT INTO `$table` ($names) VALUES ($values);\n";
            }
        }
        $sql .= "COMMIT;\nSET FOREIGN_KEY_CHECKS=1;\nALTER TABLE `subscribers` AUTO_INCREMENT=".($maximum + 1).";\n";
        if (file_put_contents($backupDir.'/restore.sql', $sql, LOCK_EX) !== strlen($sql)) {
            throw new RuntimeException('Cannot write restore SQL.');
        }
        $parent = 'subscribers';
        $child = 'subscriptions';
        $db->statement('SET SESSION FOREIGN_KEY_CHECKS=0');
    } else {
        $parent = 'resequence_preview_subscribers';
        $child = 'resequence_preview_subscriptions';
        foreach ([$parent => 'subscribers', $child => 'subscriptions'] as $temp => $source) {
            $db->statement("CREATE TEMPORARY TABLE `$temp` LIKE `$source`");
            foreach (array_chunk($snapshot[$source], 100) as $rows) {
                $db->table($temp)->insert($rows);
            }
        }
    }

    // Move parents to a disjoint temporary range to avoid primary-key collisions.
    foreach ($changes as $old => $new) {
        $db->table($parent)->where('id', $old)->update(['id' => $offset + $new]);
    }
    // Address child rows by their own stable PK so a new ID cannot be remapped twice.
    foreach ($snapshot['subscriptions'] as $row) {
        if (isset($changes[(int) $row['subscriber_id']])) {
            $db->table($child)->where('id', $row['id'])->update(['subscriber_id' => $changes[(int) $row['subscriber_id']]]);
        }
    }
    foreach ($changes as $new) {
        $db->table($parent)->where('id', $offset + $new)->update(['id' => $new]);
    }

    $expected = $snapshot;
    foreach ($expected['subscribers'] as &$row) {
        $row['id'] = $mapping[(int) $row['id']];
    }
    unset($row);
    foreach ($expected['subscriptions'] as &$row) {
        $row['subscriber_id'] = $mapping[(int) $row['subscriber_id']];
    }
    unset($row);
    usort($expected['subscribers'], fn ($a, $b) => $a['id'] <=> $b['id']);
    // Compare every field, including timestamps, account numbers and financial data.
    foreach ($tables as $table) {
        $actualTable = $table === 'subscribers' ? $parent : ($table === 'subscriptions' ? $child : $table);
        $actual = $db->table($actualTable)->orderBy('id')->get()->map(fn ($row) => (array) $row)->all();
        if ($actual != $expected[$table]) {
            throw new RuntimeException('Data comparison failed for '.$table.'; rolling back.');
        }
    }
    if ($apply) {
        $db->commit();
        $committed = true;
        $db->statement('SET SESSION FOREIGN_KEY_CHECKS='.$originalFkChecks);
        $db->statement('ALTER TABLE `subscribers` AUTO_INCREMENT='.($finalMaximum + 1));
    } else {
        $db->rollBack();
    }
    $report = ['mode' => $apply ? 'applied' : 'rehearsal', 'subscribers' => count($mapping),
        'subscriptions' => count($snapshot['subscriptions']), 'old_maximum' => $maximum,
        'new_maximum' => $finalMaximum, 'next_id' => $finalMaximum + 1,
        'changed_accounts' => count($changes), 'approved_changes' => $approved,
        'verified_tables' => $tables, 'backup_directory' => $backupDir];
    if ($apply) {
        file_put_contents($backupDir.'/report.json', json_encode($report, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
    }
    echo json_encode($report, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR), PHP_EOL;
} catch (Throwable $exception) {
    if ($db->transactionLevel()) {
        $db->rollBack();
    }
    fwrite(STDERR, ($committed ? 'Mapping committed; check finalization: ' : 'No mapping committed: ').$exception->getMessage().PHP_EOL);
    if ($backupDir) {
        fwrite(STDERR, 'Backup: '.$backupDir.PHP_EOL);
    }
    $exitCode = 1;
} finally {
    $db->statement('SET SESSION FOREIGN_KEY_CHECKS='.$originalFkChecks);
    if ($maintenanceStarted) {
        Artisan::call('up');
    }
}
exit($exitCode ?? 0);
