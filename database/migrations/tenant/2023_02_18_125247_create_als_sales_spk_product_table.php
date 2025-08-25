<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateAlsSalesSpkProductTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('als_sales_spk_product', function (Blueprint $table) {
            $table->id();
            $table->double('qty');
            $table->bigInteger('product_id')->unsigned();
            $table->bigInteger('spk_id')->unsigned();
            $table->bigInteger('order_product_id')->unsigned()->comment('nilai 0 jika tidak ada permintaan yg dipilih');
            $table->foreign('product_id')->references('id')->on('als_product')->onUpdate('cascade')->onDelete('cascade');
            $table->foreign('spk_id')->references('id')->on('als_sales_spk')->onUpdate('cascade')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('als_sales_spk_product');
    }
}
