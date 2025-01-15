<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {   
        Schema::table('orders', function (Blueprint $table) {
        $table->string('basket_id')->nullable()->index();
        $table->string('transaction_id')->nullable();
        // Remove any Stripe-specific columns if they exist
    });
        
          
    }

    public function down(): void
    {
        Schema::table('orders', static function (Blueprint $table) {
            $table->dropColumn('locale');
        });
    }
};
