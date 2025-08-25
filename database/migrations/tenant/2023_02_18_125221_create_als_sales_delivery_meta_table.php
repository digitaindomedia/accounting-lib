<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateAlsSalesDeliveryMetaTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('als_sales_delivery_meta', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('delivery_id')->unsigned();
            $table->string('meta_key',254);
            $table->longText('meta_value');
            $table->foreign('delivery_id')->references('id')->on('als_sales_delivery')->onUpdate('cascade')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('als_sales_delivery_meta');
    }
}
