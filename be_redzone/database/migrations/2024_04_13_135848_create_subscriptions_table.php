<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
  /**
   * Run the migrations.
   */
  public function up(): void
  {
    Schema::create('subscriptions', function (Blueprint $t) {
      $t->id();
      $t->foreignId('subscriber_id')->constrained()->cascadeOnDelete();
      $t->foreignId('plan_id')->constrained()->cascadeOnDelete();
      $t->date('start_date');
      $t->date('end_date')->nullable();
      $t->decimal('monthly_discount', 10, 2)->default(0);
      $t->boolean('active')->default(true);
      $t->timestamps();
    });
  }


  /**
   * Reverse the migrations.
   */
  public function down(): void
  {
    Schema::dropIfExists('subscriptions');
  }
};
