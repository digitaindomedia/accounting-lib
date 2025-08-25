<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateAlsSalesInvoiceDeliveryTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('als_sales_invoice_delivery', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('invoice_id')->unsigned();
            $table->bigInteger('delivery_id')->unsigned();
            $table->foreign('invoice_id')->references('id')->on('als_sales_invoice')->onUpdate('cascade')->onDelete('cascade');
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
        Schema::dropIfExists('als_sales_invoice_delivery');
    }
}
