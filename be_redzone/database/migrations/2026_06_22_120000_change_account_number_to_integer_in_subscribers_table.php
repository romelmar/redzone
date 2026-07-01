<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // This migration is intentionally left empty.
        // We keep account_number as a string and use account_number_int for numeric use cases.
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No changes were applied in up().
    }
};
