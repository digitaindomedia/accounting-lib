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
        Schema::create('als_aset_tetap_sales_meta', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('sales_id');
            $table->string('meta_key', 254);
            $table->mediumText('meta_value');

            $table->index('sales_id');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('als_aset_tetap_sales_meta');
    }
};
