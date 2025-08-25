<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateAlsJurnalTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('als_jurnal', function (Blueprint $table) {
            $table->id();
            $table->date('jurnal_date');
            $table->string('jurnal_no',250);
            $table->double('nominal')->default(0);
            $table->string('jurnal_type',40);
            $table->integer('status_jurnal');
            $table->datetime('created_at')->default(\Illuminate\Support\Facades\DB::raw('CURRENT_TIMESTAMP'));
            $table->integer('coa_id')->default('0');
            $table->string('transaction_type',100)->nullable()->default('NULL');
            $table->integer('created_by')->default(0);
            $table->datetime('updated_at')->default(\Illuminate\Support\Facades\DB::raw('CURRENT_TIMESTAMP'));
            $table->integer('updated_by')->default(0);
            $table->text('note')->nullable();
            $table->string('person',254)->nullable()->default('NULL');
            $table->text('reason')->nullable();
            $table->string('document',254)->nullable()->default('NULL');
            $table->index(['coa_id', 'jurnal_no']);

        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('icso_jurnal');
    }
}
