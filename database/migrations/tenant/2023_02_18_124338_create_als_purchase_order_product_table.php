<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateAlsPurchaseOrderProductTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('als_purchase_order_product', function (Blueprint $table) {
            $table->id();
            $table->double('qty');
            $table->double('price');
            $table->bigInteger('product_id')->unsigned();
            $table->bigInteger('order_id')->unsigned();
            $table->bigInteger('tax_id')->unsigned();
            $table->mediumText('tax_group')->nullable();
            $table->double('tax_percentage');
            $table->double('discount');
            $table->string('tax_type',20);
            $table->string('discount_type',20);
            $table->bigInteger('unit_id')->unsigned();
            $table->double('subtotal');
            $table->integer('multi_unit');
            $table->bigInteger('request_product_id')->comment('nilai 0 jika tidak ada permintaan yg dipilih')->unsigned();
            $table->bigInteger('invoice_id')->unsigned()->default(0);
            $table->string('service_name',250);
            $table->foreign('product_id')->references('id')->on('als_product')->onUpdate('cascade')->onDelete('cascade');
            $table->foreign('order_id')->references('id')->on('als_purchase_order')->onUpdate('cascade')->onDelete('cascade');
            $table->index(['tax_id','unit_id','request_product_id','invoice_id'], 'als_purchase_order_product_index');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('als_purchase_order_product');
    }
}
