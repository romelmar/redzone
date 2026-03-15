<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('collection_assignments', function (Blueprint $table) {
            $table->id();

            $table->foreignId('subscription_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->date('assignment_date');
            $table->string('collector_name');
            $table->text('notes')->nullable();

            $table->timestamps();

            $table->index(['assignment_date', 'collector_name']);
            $table->unique(['subscription_id', 'assignment_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('collection_assignments');
    }
};