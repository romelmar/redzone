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

        // Validate account_number values can be used as unique integer IDs.
        if (DB::table('subscribers')->where('account_number', 'NOT REGEXP', '^[0-9]+$')->exists()) {
            throw new \RuntimeException('Cannot convert subscriber IDs because some account_number values are not numeric.');
        }

        $duplicateAccountNumbers = DB::select('SELECT COUNT(*) AS cnt FROM (SELECT CAST(account_number AS UNSIGNED) AS normalized FROM subscribers GROUP BY normalized HAVING COUNT(*) > 1) t');
        if ($duplicateAccountNumbers[0]->cnt > 0) {
            throw new \RuntimeException('Cannot convert subscriber IDs because some account_number values normalize to duplicate integers.');
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

        DB::statement('INSERT INTO subscribers_new (id, name, account_number, email, phone, address, created_at, updated_at)
            SELECT CAST(account_number AS UNSIGNED) AS id, name, account_number, email, phone, address, created_at, updated_at FROM subscribers');

        // Drop the old foreign key before updating subscriber references.
        $foreignKey = DB::select("SELECT CONSTRAINT_NAME FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'subscriptions' AND CONSTRAINT_TYPE = 'FOREIGN KEY' AND CONSTRAINT_NAME = 'subscriptions_subscriber_id_foreign'");
        if (! empty($foreignKey)) {
            DB::statement('ALTER TABLE subscriptions DROP FOREIGN KEY subscriptions_subscriber_id_foreign');
        }

        // Update subscriptions to the new subscriber IDs.
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
        // Rolling back this migration is non-trivial and intentionally unsupported.
        throw new \RuntimeException('This migration cannot be safely rolled back.');
    }
};
