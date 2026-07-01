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
        $rows = DB::table('subscribers')
            ->where('account_number', 'like', '90%')
            ->orderBy('id')
            ->get(['id', 'account_number']);

        if ($rows->isEmpty()) {
            return;
        }

        $existing = DB::table('subscribers')->pluck('account_number')->toArray();
        $lookup = array_flip($existing);
        $updates = [];
        $assigned = [];

        foreach ($rows as $row) {
            $normalized = ltrim(substr($row->account_number, 1), '0');
            if ($normalized === '') {
                $normalized = '0';
            }

            $candidate = $normalized;
            $suffix = 0;
            while (isset($lookup[$candidate]) || isset($assigned[$candidate])) {
                $candidate = '1000000' . ($suffix === 0 ? '' : $suffix) . $normalized;
                $suffix++;
                if ($suffix > 10000) {
                    throw new \RuntimeException('Unable to generate a unique fallback account_number for ' . $row->account_number);
                }
            }

            if ($candidate !== $row->account_number) {
                $updates[] = ['id' => $row->id, 'account_number' => $candidate];
                $assigned[$candidate] = true;
                $lookup[$candidate] = true;
            }
        }

        if (empty($updates)) {
            return;
        }

        DB::transaction(function () use ($updates) {
            foreach ($updates as $update) {
                DB::table('subscribers')
                    ->where('id', $update['id'])
                    ->update(['account_number' => $update['account_number']]);
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        throw new \RuntimeException('This migration cannot be safely rolled back.');
    }
};
