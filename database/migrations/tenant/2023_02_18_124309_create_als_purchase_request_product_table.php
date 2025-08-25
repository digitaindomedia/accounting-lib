<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateAlsPurchaseRequestProductTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('als_purchase_request_product', function (Blueprint $table) {
            $table->id();
            $table->double('qty');
            $table->double('qty_left');
            $table->bigInteger('product_id')->unsigned();
            $table->bigInteger('request_id')->unsigned();
            $table->bigInteger('unit_id')->unsigned();
            $table->integer('multi_unit');
            $table->text('note')->nullable();
            $table->foreign('product_id')->references('id')->on('als_product')->onUpdate('cascade')->onDelete('cascade');
            $table->foreign('request_id')->references('id')->on('als_purchase_request')->onUpdate('cascade')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('als_purchase_request_product');
    }
}
