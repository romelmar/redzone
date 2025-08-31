<?php

// database/migrations/xxxx_xx_xx_add_updated_at_to_subscription_histories_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddUpdatedAtToSubscriptionHistoriesTable extends Migration
{
    public function up()
    {
        Schema::table('subscription_histories', function (Blueprint $table) {
            $table->timestamp('updated_at')->useCurrent()->nullable();
        });
    }

    public function down()
    {
        Schema::table('subscription_histories', function (Blueprint $table) {
            $table->dropColumn('updated_at');
        });
    }
}
