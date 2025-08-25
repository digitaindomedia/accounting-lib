<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateAlsSalesOrderProductTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('als_sales_order_product', function (Blueprint $table) {
            $table->id();
            $table->double('qty');
            $table->double('qty_left');
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
            $table->integer('multi_unit')->default(0);
            $table->double('subtotal');
            $table->unsignedBigInteger('invoice_id')->default(0);
            $table->foreign('product_id')->references('id')->on('als_product')->onUpdate('cascade')->onDelete('cascade');
            $table->foreign('order_id')->references('id')->on('als_sales_order')->onUpdate('cascade')->onDelete('cascade');
            $table->index(['invoice_id', 'unit_id', 'tax_id'],'sales_order_product_index_data');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('als_sales_order_product');
    }
}
