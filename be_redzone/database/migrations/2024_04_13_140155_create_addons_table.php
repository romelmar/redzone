<?php

// database/migrations/..._create_addons_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('addons', function (Blueprint $table) {
            $table->id();

            $table->foreignId('subscription_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->string('name');
            $table->decimal('amount', 10, 2);
            $table->date('bill_month'); // YYYY-MM-01

            $table->timestamps();

            $table->index(['subscription_id', 'bill_month']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('addons');
    }
};
