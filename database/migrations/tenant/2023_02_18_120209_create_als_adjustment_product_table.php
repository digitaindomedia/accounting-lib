<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateAlsAdjustmentProductTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('als_adjustment_product', function (Blueprint $table) {
            $table->id();
            $table->double('qty_tercatat');
            $table->double('qty_actual');
            $table->double('qty_selisih');
            $table->double('hpp');
            $table->double('subtotal');
            $table->bigInteger('adjustment_id')->unsigned();
            $table->bigInteger('product_id')->unsigned();
            $table->bigInteger('unit_id')->unsigned();
            $table->bigInteger('coa_id')->unsigned();
            $table->foreign('product_id')->references('id')->on('als_product')->onUpdate('cascade')->onDelete('cascade');
            $table->foreign('adjustment_id')->references('id')->on('als_adjustment')->onUpdate('cascade')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('als_adjustment_product');
    }
}
