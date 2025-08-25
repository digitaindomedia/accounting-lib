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
        Schema::create('als_aset_tetap', function (Blueprint $table) {
            $table->id();
            $table->string('nama_aset', 254);
            $table->string('no_aset', 254);
            $table->date('aset_tetap_date');
            $table->double('harga_beli');
            $table->integer('aset_tetap_coa_id');
            $table->integer('dari_akun_coa_id');
            $table->text('note');
            $table->integer('status_penyusutan');
            $table->double('nilai_penyusutan');
            $table->bigInteger('akumulasi_penyusutan_coa_id')->unsigned();
            $table->bigInteger('penyusutan_coa_id')->unsigned();
            $table->string('metode_penyusutan', 100);
            $table->date('tanggal_mulai_penyusutan')->nullable();
            $table->timestamps();
            $table->integer('created_by');
            $table->integer('updated_by');
            $table->string('status_aset_tetap', 120);
            $table->integer('masa_manfaat');
            $table->double('nilai_residu');
            $table->string('pilihan_nilai', 50);
            $table->double('dpp')->default(0);
            $table->double('ppn')->default(0);
            $table->string('pilihan', 100);
            $table->integer('qty')->default(1);
            $table->text('reason')->nullable();
            $table->double('nilai_akum_penyusutan')->default(0);
            $table->date('tanggal_input_aset')->nullable();
            $table->bigInteger('akun_selisih')->default(0)->unsigned();
            $table->integer('is_saldo_awal')->default(0);
            $table->index('status_aset_tetap');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('als_aset_tetap');
    }
};
