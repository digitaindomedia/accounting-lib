<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateAlsJurnalTransactionsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('als_jurnal_transactions', function (Blueprint $table) {
            $table->id();
            $table->string('transaction_no',254);
            $table->date('transaction_date');
            $table->dateTime('transaction_datetime')->default(\Illuminate\Support\Facades\DB::raw('CURRENT_TIMESTAMP'));
            $table->text('note')->nullable();
            $table->double('debet');
            $table->double('kredit');
            $table->integer('transaction_status');
            $table->string('transaction_code',100);
            $table->bigInteger('coa_id')->unsigned();
            $table->integer('transaction_id');
            $table->integer('transaction_sub_id');
            $table->datetime('created_at')->default(\Illuminate\Support\Facades\DB::raw('CURRENT_TIMESTAMP'));
            $table->integer('created_by');
            $table->datetime('updated_at')->default(\Illuminate\Support\Facades\DB::raw('CURRENT_TIMESTAMP'));
            $table->integer('updated_by');
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
        Schema::dropIfExists('als_jurnal_transactions');
    }
}
