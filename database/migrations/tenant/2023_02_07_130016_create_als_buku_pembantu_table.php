<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateAlsBukuPembantuTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('als_buku_pembantu', function (Blueprint $table) {
            $table->id();
            $table->string('field_name',254);
            $table->string('ref_no',254);
            $table->date('ref_date');
            $table->text('note');
            $table->string('status_ref',25);
            $table->integer('jurnal_id');
            $table->integer('jurnal_akun_id');
            $table->datetime('created_at')->default(\Illuminate\Support\Facades\DB::raw('CURRENT_TIMESTAMP'));;
            $table->integer('created_by');
            $table->datetime('updated_at')->default(\Illuminate\Support\Facades\DB::raw('CURRENT_TIMESTAMP'));;
            $table->integer('updated_by');
            $table->double('nominal')->default(0);
            $table->integer('coa_id')->default(0);
            $table->string('input_type',60);
            $table->index(['coa_id']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('als_buku_pembantu');
    }
}
