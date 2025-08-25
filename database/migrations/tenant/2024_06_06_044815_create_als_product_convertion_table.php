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
        Schema::create('als_product_convertion', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('product_id'); // Equivalent to BIGINT UNSIGNED.
            $table->unsignedBigInteger('unit_id'); // Equivalent to BIGINT UNSIGNED.
            $table->double('nilai')->default(0); // DOUBLE with default 0.
            $table->double('nilai_terkecil')->default(0); // DOUBLE with default 0.
            $table->unsignedBigInteger('base_unit_id')->default(0); // BIGINT UNSIGNED with default 0.
            $table->double('price')->default(0); // DOUBLE with default 0.
            $table->foreign('product_id')->references('id')->on('als_product')->onUpdate('cascade')->onDelete('cascade');
            $table->foreign('unit_id')->references('id')->on('als_unit')->onUpdate('cascade')->onDelete('cascade');
            $table->foreign('base_unit_id')->references('id')->on('als_unit')->onUpdate('cascade')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('als_product_convertion');
    }
};
