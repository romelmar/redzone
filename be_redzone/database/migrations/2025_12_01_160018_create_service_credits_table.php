<?php

// database/migrations/..._create_service_credits_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  public function up(): void {
    Schema::create('service_credits', function (Blueprint $t) {
      $t->id();
      $t->foreignId('subscription_id')->constrained()->cascadeOnDelete();
      $t->date('bill_month'); // 1st day of month credited
      $t->unsignedInteger('outage_days')->default(0); // number of days to deduct
      $t->text('reason')->nullable();
      $t->timestamps();
    });
  }
  public function down(): void { Schema::dropIfExists('service_credits'); }
};
