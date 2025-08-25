<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
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
        Schema::create('als_aset_tetap_invoice', function (Blueprint $table) {
            $table->id();
            $table->date('invoice_date');
            $table->string('invoice_no', 254);
            $table->unsignedBigInteger('order_id');
            $table->text('note');
            $table->string('invoice_status', 120);
            $table->timestamps();
            $table->integer('created_by');
            $table->integer('updated_by');
            $table->double('dpp');
            $table->double('ppn');
            $table->double('total');
            $table->double('total_tagihan');
            $table->string('faktur', 254)->nullable();
            $table->string('tanggal_faktur', 254)->nullable();
            $table->string('reason', 254)->nullable();
            $table->integer('is_saldo_awal')->default(0);
            $table->date('due_date')->nullable();

            $table->index('order_id');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('als_aset_tetap_invoice');
    }
};
