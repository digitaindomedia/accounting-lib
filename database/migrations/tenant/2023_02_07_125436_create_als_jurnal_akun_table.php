<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateAlsJurnalAkunTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('als_jurnal_akun', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('jurnal_id')->unsigned();
            $table->bigInteger('coa_id')->unsigned();
            $table->longText('data_sess')->nullable();
            $table->double('debet')->default(0);
            $table->double('kredit')->default(0);
            $table->double('nominal')->default(0);
            $table->text('note')->nullable();
            $table->foreign('jurnal_id')->references('id')->on('als_jurnal')->onUpdate('cascade')->onDelete('cascade');
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
        Schema::dropIfExists('create_als_jurnal_akun');
    }
}
