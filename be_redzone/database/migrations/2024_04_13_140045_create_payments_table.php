<?php

// database/migrations/..._create_payments_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();

            $table->foreignId('subscription_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->decimal('amount', 10, 2);
            $table->date('payment_date')->useCurrent();
            $table->string('payment_type')->default('payment'); 
            // payment | offset | adjustment

            $table->text('remarks')->nullable();
            $table->timestamps();

            $table->index(['subscription_id', 'payment_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};

