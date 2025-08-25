<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateAlsPurchaseBastProductTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('als_purchase_bast_product', function (Blueprint $table) {
            $table->id();
            $table->double('qty');
            $table->double('qty_left');
            $table->double('hpp_price');
            $table->double('buy_price');
            $table->string('service_name',254)->nullable();
            $table->bigInteger('bast_id')->unsigned();
            $table->bigInteger('tax_id')->unsigned();
            $table->double('tax_percentage')->default(0);
            $table->double('discount');
            $table->string('tax_type',20)->nullable();
            $table->string('discount_type',20)->nullable();
            $table->bigInteger('order_product_id')->unsigned()->comment('nilai 0 jika tidak ada permintaan yg dipilih');
            $table->foreign('bast_id')->references('id')->on('als_purchase_bast')->onUpdate('cascade')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('als_purchase_bast_product');
    }
}
