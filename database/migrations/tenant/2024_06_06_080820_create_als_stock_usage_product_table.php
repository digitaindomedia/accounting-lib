<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('als_stock_usage_product', function (Blueprint $table) {
            $table->id();
            $table->double('qty');
            $table->double('hpp');
            $table->double('subtotal');
            $table->unsignedBigInteger('usage_stock_id');
            $table->unsignedBigInteger('product_id');
            $table->unsignedBigInteger('coa_id');
            $table->unsignedBigInteger('unit_id');
            $table->text('note')->nullable();
            $table->foreign('usage_stock_id')->references('id')->on('als_stock_usage')->onUpdate('cascade')->onDelete('cascade');
            $table->foreign('product_id')->references('id')->on('als_product')->onUpdate('cascade')->onDelete('cascade');
            $table->foreign('unit_id')->references('id')->on('als_unit')->onUpdate('cascade')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('als_stock_usage_product');
    }
};
