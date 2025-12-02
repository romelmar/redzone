<?php

// database/migrations/..._create_payments_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  public function up(): void {
    Schema::create('payments', function (Blueprint $t) {
      $t->id();
      $t->foreignId('subscription_id')->constrained()->cascadeOnDelete();
      $t->decimal('amount', 10, 2);
      $t->date('paid_at');
      $t->string('reference')->nullable();
      $t->text('notes')->nullable();
      $t->timestamps();
    });
  }
  public function down(): void { Schema::dropIfExists('payments'); }
};
