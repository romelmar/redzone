<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        $rows = DB::table('subscribers')
            ->where('account_number', 'like', '0%')
            ->pluck('account_number');

        if ($rows->isEmpty()) {
            return;
        }

        $transformed = $rows->map(fn ($account) => '9'.$account)->all();
        $conflicts = DB::table('subscribers')
            ->whereIn('account_number', $transformed)
            ->exists();

        if ($conflicts) {
            throw new RuntimeException('Cannot prefix account numbers because transformed values already exist.');
        }

        DB::table('subscribers')
            ->where('account_number', 'like', '0%')
            ->update(['account_number' => DB::raw("CONCAT('9', account_number)")]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // This migration is intentionally irreversible in a safe way.
    }
};
