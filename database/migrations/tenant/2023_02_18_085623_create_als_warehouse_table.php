<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateAlsWarehouseTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('als_warehouse', function (Blueprint $table) {
            $table->id();
            $table->string('warehouse_name',254);
            $table->string('warehouse_code',254);
            $table->text('warehouse_address');
            $table->mediumText('warehouse_meta_field')->nullable();
            $table->datetime('created_at')->default(\Illuminate\Support\Facades\DB::raw('CURRENT_TIMESTAMP'));;
            $table->integer('created_by');
            $table->datetime('updated_at')->default(\Illuminate\Support\Facades\DB::raw('CURRENT_TIMESTAMP'));;
            $table->integer('updated_by');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('als_warehouse');
    }
}
