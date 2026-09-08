<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

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

        $rowsToMove = DB::table('subscribers')
            ->where('id', '>=', 100000000)
            ->orderBy('id')
            ->get(['id', 'account_number', 'name', 'email', 'phone', 'address', 'created_at', 'updated_at']);

        if ($rowsToMove->isEmpty()) {
            return;
        }

        $existingIds = DB::table('subscribers')
            ->orderBy('id')
            ->pluck('id')
            ->toArray();

        $usedIds = array_flip($existingIds);
        $nextId = 1;
        $newIds = [];

        foreach ($rowsToMove as $row) {
            while (isset($usedIds[$nextId])) {
                $nextId++;
            }

            $newIds[$row->id] = $nextId;
            $usedIds[$nextId] = true;
            $nextId++;
        }

        Schema::disableForeignKeyConstraints();

        Schema::create('subscribers_new', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('account_number')->unique();
            $table->string('email')->default('');
            $table->string('phone')->nullable();
            $table->string('address')->nullable();
            $table->timestamps();
        });

        $allRows = DB::table('subscribers')
            ->orderBy('id')
            ->get(['id', 'name', 'account_number', 'email', 'phone', 'address', 'created_at', 'updated_at']);

        $insertRows = [];

        foreach ($allRows as $row) {
            $id = $row->id;
            if (isset($newIds[$id])) {
                $id = $newIds[$id];
            }

            $insertRows[] = [
                'id' => $id,
                'name' => $row->name,
                'account_number' => $row->account_number,
                'email' => $row->email,
                'phone' => $row->phone,
                'address' => $row->address,
                'created_at' => $row->created_at,
                'updated_at' => $row->updated_at,
            ];
        }

        foreach (array_chunk($insertRows, 200) as $chunk) {
            DB::table('subscribers_new')->insert($chunk);
        }

        $foreignKey = DB::select("SELECT CONSTRAINT_NAME FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'subscriptions' AND CONSTRAINT_TYPE = 'FOREIGN KEY' AND CONSTRAINT_NAME = 'subscriptions_subscriber_id_foreign'");
        if (! empty($foreignKey)) {
            DB::statement('ALTER TABLE subscriptions DROP FOREIGN KEY subscriptions_subscriber_id_foreign');
        }

        DB::statement('UPDATE subscriptions s JOIN subscribers old ON s.subscriber_id = old.id JOIN subscribers_new n ON old.account_number = n.account_number SET s.subscriber_id = n.id');

        Schema::drop('subscribers');
        Schema::rename('subscribers_new', 'subscribers');

        Schema::table('subscriptions', function (Blueprint $table) {
            $table->foreign('subscriber_id')->references('id')->on('subscribers')->onDelete('cascade');
        });

        Schema::enableForeignKeyConstraints();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        throw new \RuntimeException('This migration cannot be safely rolled back.');
    }
};
