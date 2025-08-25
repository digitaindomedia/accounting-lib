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
        Schema::create('als_sales_downpayment_meta', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('dp_id'); // Equivalent to BIGINT UNSIGNED.
            $table->string('meta_key', 254); // VARCHAR(254).
            $table->mediumText('meta_value')->nullable();
            $table->foreign('dp_id')->references('id')->on('als_sales_downpayment')->onUpdate('cascade')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('als_sales_downpayment_meta');
    }
};
