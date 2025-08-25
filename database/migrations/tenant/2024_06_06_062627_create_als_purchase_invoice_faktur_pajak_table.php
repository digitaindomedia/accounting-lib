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
        Schema::create('als_purchase_invoice_faktur_pajak', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('invoice_id'); // Equivalent to BIGINT UNSIGNED.
            $table->date('faktur_date'); // DATE column.
            $table->string('faktur_no', 25); // VARCHAR(25) column.
            $table->double('faktur_nominal'); // DOUBLE column.
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
        Schema::dropIfExists('als_purchase_invoice_faktur_pajak');
    }
};
