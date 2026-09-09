<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->string('collector_name')->nullable();
            $table->string('payment_method', 20)->default('unspecified');
            $table->softDeletes();
            $table->index(['payment_date', 'collector_name', 'payment_method'], 'payments_collection_lookup');
        });
        Schema::create('payment_audits', function (Blueprint $table) {
            $table->id();
            // Deliberately independent of cascading deletes: this is retained history.
            $table->unsignedBigInteger('payment_id')->index();
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->string('actor_name');
            $table->string('action', 20);
            $table->text('reason')->nullable();
            $table->json('before')->nullable();
            $table->json('after')->nullable();
            $table->timestamp('created_at');
        });
        DB::table('payments')->orderBy('id')->chunkById(500, function ($rows) {
            foreach ($rows as $row) {
                $snapshot = (array) $row;
                unset($snapshot['request_key'], $snapshot['request_hash']);
                DB::table('payment_audits')->insert([
                    'payment_id' => $row->id, 'actor_name' => 'System', 'action' => 'baseline',
                    'reason' => 'Existing record when audit tracking was enabled; earlier changes are unknown.',
                    'after' => json_encode($snapshot, JSON_THROW_ON_ERROR), 'created_at' => now(),
                ]);
            }
        });
        Schema::create('collector_remittances', function (Blueprint $table) {
            $table->id();
            $table->date('collection_date');
            $table->string('collector_name');
            $table->string('payment_method', 20);
            $table->decimal('amount', 12, 2);
            $table->string('reference');
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('recorded_by')->nullable();
            $table->string('recorded_by_name');
            $table->uuid('request_key')->unique();
            $table->string('request_hash', 64);
            $table->timestamp('created_at');
            $table->index(['collection_date', 'collector_name', 'payment_method'], 'remittance_collection_lookup');
            $table->timestamp('voided_at')->nullable();
            $table->text('void_reason')->nullable();
            $table->unsignedBigInteger('voided_by')->nullable();
            $table->string('voided_by_name')->nullable();
        });
    }

    public function down(): void
    {
        throw new RuntimeException('This migration retains financial audit records and cannot be rolled back destructively.');
    }
};
