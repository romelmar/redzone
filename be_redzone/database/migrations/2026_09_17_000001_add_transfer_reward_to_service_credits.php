<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up(): void {
  Schema::table('service_credits', function (Blueprint $table) {
   $table->string('transfer_reward_key')->nullable()->unique();
   $table->string('previous_provider')->nullable();
   $table->string('granted_by_name')->nullable();
  });
 }
 public function down(): void {
  Schema::table('service_credits', function (Blueprint $table) {
   $table->dropUnique(['transfer_reward_key']);
   $table->dropColumn(['transfer_reward_key','previous_provider','granted_by_name']);
  });
 }
};
