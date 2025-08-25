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
        Schema::create('als_tax_group', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tax_id');
            $table->string('majemuk', 10)->default('no');
            $table->unsignedBigInteger('id_tax');
            $table->foreign('tax_id')->references('id')->on('als_tax')->onUpdate('cascade')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('als_tax_group');
    }
};
