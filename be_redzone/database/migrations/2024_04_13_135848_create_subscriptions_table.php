<?php

// database/migrations/..._create_subscriptions_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();

            $table->foreignId('subscriber_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignId('plan_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->date('start_date');
            $table->date('end_date')->nullable();

            $table->decimal('monthly_discount', 10, 2)->default(0);

            // STATUS HANDLING
            $table->boolean('active')->default(true);
            $table->timestamp('deactivated_at')->nullable();
            $table->integer('reactivated_days_passed')->default(0);

            $table->timestamps();

            $table->index(['subscriber_id', 'plan_id']);
            $table->index('active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscriptions');
    }
};

