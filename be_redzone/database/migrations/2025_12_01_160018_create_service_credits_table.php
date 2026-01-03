<?php

// database/migrations/..._create_service_credits_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('service_credits', function (Blueprint $table) {
            $table->id();

            $table->foreignId('subscription_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->integer('outage_days');
            $table->decimal('amount', 10, 2);
            $table->date('credit_month'); // YYYY-MM-01
            $table->text('reason')->nullable();

            $table->timestamps();

            $table->index(['subscription_id', 'credit_month']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_credits');
    }
};
