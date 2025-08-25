<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateAlsSalesReturProductTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('als_sales_retur_product', function (Blueprint $table) {
            $table->id();
            $table->double('qty');
            $table->double('hpp_price');
            $table->double('sell_price');
            $table->bigInteger('product_id')->unsigned();
            $table->bigInteger('retur_id')->unsigned();
            $table->unsignedBigInteger('tax_id');
            $table->double('tax_percentage');
            $table->double('discount');
            $table->string('tax_type',20);
            $table->string('discount_type',20);
            $table->unsignedBigInteger('unit_id');
            $table->integer('multi_unit')->default(0);
            $table->unsignedBigInteger('delivery_product_id')->comment('nilai 0 jika tidak ada permintaan yg dipilih');
            $table->double('subtotal');
            $table->unsignedBigInteger('order_product_id');
            $table->foreign('product_id')->references('id')->on('als_product')->onUpdate('cascade')->onDelete('cascade');
            $table->foreign('retur_id')->references('id')->on('als_sales_retur')->onUpdate('cascade')->onDelete('cascade');
            $table->index(['unit_id','order_product_id','tax_id'],'als_sales_retur_product_index');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('als_sales_retur_product');
    }
}
