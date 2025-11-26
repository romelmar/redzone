<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('plans', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->string('speed')->nullable();          // e.g. “50 Mbps”
            $t->decimal('price', 10, 2);
            $t->text('description')->nullable();
            $t->timestamps();
        });
    }


    public function down(): void
    {
        Schema::dropIfExists('plans');
    }
};
