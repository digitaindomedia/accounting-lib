<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateAlsSalesQuotationProductTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('als_sales_quotation_product', function (Blueprint $table) {
            $table->id();
            $table->double('qty');
            $table->double('qty_left');
            $table->double('price')->default(0);
            $table->bigInteger('product_id')->unsigned();
            $table->bigInteger('quotation_id')->unsigned();
            $table->bigInteger('tax_id')->unsigned()->nullable();
            $table->mediumText('tax_group')->nullable();
            $table->double('tax_percentage')->default(0);
            $table->string('tax_type',20)->nullable();
            $table->bigInteger('unit_id')->unsigned();
            $table->integer('multi_unit');
            $table->double('subtotal')->default(0);
            $table->text('note')->nullable();
            $table->foreign('product_id')->references('id')->on('als_product')->onUpdate('cascade')->onDelete('cascade');
            $table->foreign('quotation_id')->references('id')->on('als_sales_quotation')->onUpdate('cascade')->onDelete('cascade');
            $table->foreign('tax_id')->references('id')->on('als_tax')->onUpdate('cascade')->onDelete('cascade');
            $table->index(['unit_id', 'tax_id'], 'sales_quotation_product_index_data');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('als_sales_quotation_product');
    }
}
