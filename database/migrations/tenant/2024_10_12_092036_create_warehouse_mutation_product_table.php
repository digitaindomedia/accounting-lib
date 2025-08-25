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
        Schema::create('als_warehouse_mutation_product', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('mutation_id');
            $table->bigInteger('product_id')->unsigned();
            $table->unsignedBigInteger('unit_id');
            $table->double('qty');
            $table->double('price')->default(0);
            $table->foreign('product_id')->references('id')->on('als_product')->onUpdate('cascade')->onDelete('cascade');
            $table->foreign('mutation_id')->references('id')->on('als_warehouse_mutation')->onUpdate('cascade')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('als_warehouse_mutation_product');
    }
};
