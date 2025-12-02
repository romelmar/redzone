<?php

// database/migrations/..._create_addons_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  public function up(): void {
    Schema::create('addons', function (Blueprint $t) {
      $t->id();
      $t->foreignId('subscription_id')->constrained()->cascadeOnDelete();
      $t->string('name');
      $t->decimal('amount', 10, 2);
      $t->date('bill_month'); // use first day of the month (e.g., 2025-11-01)
      $t->timestamps();
    });
  }
  public function down(): void { Schema::dropIfExists('addons'); }
};
