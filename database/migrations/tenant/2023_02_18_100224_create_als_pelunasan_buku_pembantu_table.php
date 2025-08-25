<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateAlsPelunasanBukuPembantuTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('als_pelunasan_buku_pembantu', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('buku_pembantu_id')->unsigned();
            $table->string('ref_no',254);
            $table->date('payment_date');
            $table->text('note')->nullable();
            $table->bigInteger('jurnal_id')->unsigned();
            $table->bigInteger('jurnal_akun_id')->unsigned();
            $table->double('nominal');
            $table->datetime('created_at')->default(\Illuminate\Support\Facades\DB::raw('CURRENT_TIMESTAMP'));;
            $table->integer('created_by');
            $table->datetime('updated_at')->default(\Illuminate\Support\Facades\DB::raw('CURRENT_TIMESTAMP'));;
            $table->integer('updated_by');
            $table->foreign('buku_pembantu_id')->references('id')->on('als_buku_pembantu')->onUpdate('cascade')->onDelete('cascade');
            $table->foreign('jurnal_id')->references('id')->on('als_jurnal')->onUpdate('cascade')->onDelete('cascade');
            $table->foreign('jurnal_akun_id')->references('id')->on('als_jurnal_akun')->onUpdate('cascade')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('als_pelunasan_buku_pembantu');
    }
}
