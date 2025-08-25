<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateAlsInventoryTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('als_inventory', function (Blueprint $table) {
            $table->id();
            $table->dateTime('inventory_date');
            $table->double('qty_in')->default(0);
            $table->double('qty_out')->default(0);
            $table->double('nominal');
            $table->bigInteger('warehouse_id')->unsigned();
            $table->bigInteger('product_id')->unsigned();
            $table->bigInteger('coa_id')->unsigned();
            $table->string('transaction_code', 40);
            $table->bigInteger('transaction_id')->unsigned();
            $table->bigInteger('transaction_sub_id')->unsigned();
            $table->bigInteger('unit_id')->unsigned();
            $table->double('total_in')->default(0);
            $table->double('total_out')->default(0);
            $table->text('note')->nullable();
            $table->datetime('created_at')->default(\Illuminate\Support\Facades\DB::raw('CURRENT_TIMESTAMP'));;
            $table->integer('created_by');
            $table->datetime('updated_at')->default(\Illuminate\Support\Facades\DB::raw('CURRENT_TIMESTAMP'));;
            $table->integer('updated_by');
            $table->index(['coa_id','product_id','warehouse_id','unit_id','inventory_date','transaction_code'],'inventory_add_index');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('als_inventory');
    }
}
