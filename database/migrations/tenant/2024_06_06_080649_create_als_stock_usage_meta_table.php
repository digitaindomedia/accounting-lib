<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('als_stock_usage_meta', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('usage_stock_id');
            $table->string('meta_key', 254);
            $table->mediumText('meta_value');

            $table->index('usage_stock_id');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('als_stock_usage_meta');
    }
};
