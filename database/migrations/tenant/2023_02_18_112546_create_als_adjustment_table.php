<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateAlsAdjustmentTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('als_adjustment', function (Blueprint $table) {
            $table->id();
            $table->string('ref_no',254);
            $table->date('adjustment_date');
            $table->text('note')->nullable();
            $table->string('adjustment_status',50);
            $table->string('adjustment_type',50)->comment('hanya ada adjustment qty dan value');
            $table->double('total')->default(0);
            $table->integer('warehouse_id');
            $table->string('document',254)->nullable();
            $table->integer('coa_adjustment_id');
            $table->datetime('created_at')->default(\Illuminate\Support\Facades\DB::raw('CURRENT_TIMESTAMP'));;
            $table->integer('created_by');
            $table->datetime('updated_at')->default(\Illuminate\Support\Facades\DB::raw('CURRENT_TIMESTAMP'));;
            $table->integer('updated_by');
            $table->string('reason', 254)->nullable();

            $table->index('warehouse_id');
            $table->index('coa_adjustment_id');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('als_adjustment');
    }
}
