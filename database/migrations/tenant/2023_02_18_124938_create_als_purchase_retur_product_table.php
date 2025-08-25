<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateAlsPurchaseReturProductTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('als_purchase_retur_product', function (Blueprint $table) {
            $table->id();
            $table->double('qty');
            $table->double('hpp_price');
            $table->double('buy_price');
            $table->bigInteger('product_id')->unsigned();
            $table->bigInteger('retur_id')->unsigned();
            $table->bigInteger('tax_id')->unsigned();
            $table->double('tax_percentage');
            $table->double('discount');
            $table->string('tax_type',20);
            $table->string('discount_type',20);
            $table->bigInteger('unit_id')->unsigned();
            $table->integer('multi_unit')->default(0);
            $table->bigInteger('receive_product_id')->comment('nilai 0 jika tidak ada penerimaan yg dipilih')->unsigned();
            $table->double('subtotal');
            $table->bigInteger('order_product_id')->unsigned();
            $table->foreign('product_id')->references('id')->on('als_product')->onUpdate('cascade')->onDelete('cascade');
            $table->foreign('retur_id')->references('id')->on('als_purchase_retur')->onUpdate('cascade')->onDelete('cascade');
            $table->index(['unit_id', 'order_product_id','receive_product_id'],'als_purchase_retur_product');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('als_purchase_retur_product');
    }
}
