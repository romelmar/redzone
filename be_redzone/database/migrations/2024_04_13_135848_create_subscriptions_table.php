<?php

// database/migrations/..._create_subscriptions_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  public function up(): void {
    Schema::create('subscriptions', function (Blueprint $t) {
      $t->id();
      $t->foreignId('subscriber_id')->constrained()->cascadeOnDelete();
      $t->foreignId('plan_id')->constrained()->cascadeOnDelete();
      $t->date('start_date');
      $t->date('end_date')->nullable();
      $t->decimal('monthly_discount', 10, 2)->default(0); // per-subscriber monthly discount
      $t->boolean('active')->default(true);
      $t->timestamps();
    });
  }
  public function down(): void { Schema::dropIfExists('subscriptions'); }
};
