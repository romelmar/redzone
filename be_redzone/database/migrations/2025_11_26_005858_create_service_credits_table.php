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
        Schema::create('service_credits', function (Blueprint $t) {
            $t->id();
            $t->foreignId('subscription_id')->constrained()->cascadeOnDelete();
            $t->date('bill_month');
            $t->unsignedInteger('outage_days')->default(0);
            $t->text('reason')->nullable();
            $t->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('service_credits');
    }
};
