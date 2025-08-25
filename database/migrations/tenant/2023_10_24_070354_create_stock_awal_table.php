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
        Schema::create('als_stock_awal', function (Blueprint $table) {
            $table->id();
            $table->date('stock_date');
            $table->bigInteger('product_id')->unsigned();
            $table->double('qty');
            $table->double('total');
            $table->bigInteger('unit_id')->unsigned();
            $table->bigInteger('coa_id')->unsigned();
            $table->bigInteger('warehouse_id')->unsigned();
            $table->double('nominal')->default(0);
            $table->datetime('created_at')->default(\Illuminate\Support\Facades\DB::raw('CURRENT_TIMESTAMP'));;
            $table->integer('created_by');
            $table->datetime('updated_at')->default(\Illuminate\Support\Facades\DB::raw('CURRENT_TIMESTAMP'));;
            $table->integer('updated_by');
            $table->foreign('product_id')->references('id')->on('als_product')->onUpdate('cascade')->onDelete('cascade');
            $table->foreign('warehouse_id')->references('id')->on('als_warehouse')->onUpdate('cascade')->onDelete('cascade');
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
        Schema::dropIfExists('als_stock_awal');
    }
};
