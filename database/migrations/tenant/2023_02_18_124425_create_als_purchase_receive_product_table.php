<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateAlsPurchaseReceiveProductTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('als_purchase_receive_product', function (Blueprint $table) {
            $table->id();
            $table->double('qty');
            $table->double('qty_left');
            $table->double('hpp_price');
            $table->double('buy_price');
            $table->bigInteger('product_id')->unsigned();
            $table->bigInteger('receive_id')->unsigned();
            $table->bigInteger('tax_id')->unsigned();
            $table->double('tax_percentage')->default(0);
            $table->mediumText('tax_group')->nullable();
            $table->double('discount');
            $table->double('subtotal');
            $table->string('tax_type',20);
            $table->string('discount_type',20);
            $table->bigInteger('unit_id')->unsigned();
            $table->integer('multi_unit');
            $table->bigInteger('order_product_id')->comment('nilai 0 jika tidak ada order yg dipilih')->unsigned();
            $table->foreign('product_id')->references('id')->on('als_product')->onUpdate('cascade')->onDelete('cascade');
            $table->foreign('receive_id')->references('id')->on('als_purchase_receive')->onUpdate('cascade')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('als_purchase_receive_product');
    }
}
