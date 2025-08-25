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
        Schema::create('als_purchase_invoice_dp', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('invoice_id'); // Equivalent to BIGINT UNSIGNED.
            $table->unsignedBigInteger('dp_id');
            $table->foreign('dp_id')->references('id')->on('als_purchase_downpayment')->onUpdate('cascade')->onDelete('cascade');
            $table->foreign('invoice_id')->references('id')->on('als_purchase_invoice')->onUpdate('cascade')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('als_purchase_invoice_dp');
    }
};
