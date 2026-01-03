<?php

// database/migrations/..._create_subscriptions_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('subscription_events', function (Blueprint $table) {
            $table->id();

            $table->foreignId('subscription_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->string('type'); // activate, deactivate, payment, billing
            $table->string('title')->nullable();
            $table->text('description')->nullable();

            $table->timestamp('event_at')->useCurrent();
            $table->timestamps();

            $table->index(['subscription_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscription_events');
    }
};


