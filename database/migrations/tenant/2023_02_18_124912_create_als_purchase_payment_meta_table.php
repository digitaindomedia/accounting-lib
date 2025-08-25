<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateAlsPurchasePaymentMetaTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('als_purchase_payment_meta', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('payment_id')->unsigned();
            $table->string('meta_key',254);
            $table->longText('meta_value');
            $table->foreign('payment_id')->references('id')->on('als_purchase_payment')->onUpdate('cascade')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('als_purchase_payment_meta');
    }
}
