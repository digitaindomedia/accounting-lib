<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateAlsSaldoAwalAkunTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('als_saldo_awal_akun', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('saldo_id')->unsigned();
            $table->bigInteger('coa_id')->unsigned();
            $table->double('debet');
            $table->double('kredit');
            $table->foreign('saldo_id')->references('id')->on('als_saldo_awal')->onUpdate('cascade')->onDelete('cascade');
            $table->foreign('coa_id')->references('id')->on('als_coa')->onUpdate('cascade')->onDelete('cascade');

        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('als_saldo_awal_akun');
    }
}
