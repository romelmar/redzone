<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscription_rates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subscription_id')->constrained()->cascadeOnDelete();
            $table->date('effective_from');
            $table->decimal('price', 10, 2);
            $table->decimal('discount', 10, 2)->default(0);
            $table->unique(['subscription_id', 'effective_from']);
        });

        // Existing data has no rate history; preserve the currently known baseline.
        DB::table('subscriptions')->join('plans', 'plans.id', '=', 'subscriptions.plan_id')
            ->select('subscriptions.id', 'subscriptions.start_date', 'subscriptions.monthly_discount', 'plans.price')
            ->orderBy('subscriptions.id')->chunk(500, function ($rows) {
                foreach ($rows as $row) {
                    DB::table('subscription_rates')->insert([
                        'subscription_id' => $row->id,
                        'effective_from' => substr($row->start_date, 0, 7).'-01',
                        'price' => $row->price,
                        'discount' => $row->monthly_discount ?? 0,
                    ]);
                }
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscription_rates');
    }
};
