<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateAlsSalesDeliveryProductTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('als_sales_delivery_product', function (Blueprint $table) {
            $table->id();
            $table->double('qty');
            $table->double('qty_left');
            $table->double('hpp_price');
            $table->double('sell_price');
            $table->bigInteger('product_id')->unsigned();
            $table->bigInteger('delivery_id')->unsigned();
            $table->bigInteger('tax_id')->unsigned();
            $table->double('tax_percentage');
            $table->double('discount');
            $table->double('subtotal');
            $table->string('tax_type',20);
            $table->string('discount_type',20);
            $table->bigInteger('unit_id')->unsigned();
            $table->integer('multi_unit')->default(0);
            $table->bigInteger('order_product_id')->unsigned()->comment('nilai 0 jika tidak ada permintaan yg dipilih');
            $table->foreign('product_id')->references('id')->on('als_product')->onUpdate('cascade')->onDelete('cascade');
            $table->foreign('delivery_id')->references('id')->on('als_sales_delivery')->onUpdate('cascade')->onDelete('cascade');
            $table->index(['unit_id', 'order_product_id','tax_id']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('als_sales_delivery_product');
    }
}
