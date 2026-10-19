<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('online_payment_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subscription_id')->constrained()->restrictOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('reference')->unique();
            $table->string('token_hash', 64)->unique();
            $table->text('token_encrypted');
            $table->string('mode', 8);
            $table->string('status', 24)->default('ready')->index();
            $table->date('billing_month');
            $table->unsignedBigInteger('amount_centavos');
            $table->json('snapshot');
            $table->string('checkout_id')->nullable()->unique();
            $table->text('checkout_url')->nullable();
            $table->string('provider_payment_id')->nullable()->unique();
            $table->foreignId('payment_id')->nullable()->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('fee_centavos')->nullable();
            $table->bigInteger('net_centavos')->nullable();
            $table->string('settlement_reference')->nullable();
            $table->timestamp('settled_at')->nullable();
            $table->text('review_reason')->nullable();
            $table->dateTime('expires_at');
            $table->timestamps();
        });
        Schema::table('payments', function (Blueprint $table) {
            $table->unsignedBigInteger('gateway_request_id')->nullable()->unique();
        });
        Schema::create('online_payment_events', function (Blueprint $table) {
            $table->id();
            $table->string('event_key', 128)->unique();
            $table->foreignId('online_payment_request_id')->constrained()->restrictOnDelete();
            $table->string('outcome');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('online_payment_events');
        Schema::table('payments', fn (Blueprint $table) => $table->dropColumn('gateway_request_id'));
        Schema::dropIfExists('online_payment_requests');
    }
};
