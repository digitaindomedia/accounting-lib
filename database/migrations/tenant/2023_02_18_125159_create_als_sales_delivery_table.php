<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateAlsSalesDeliveryTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('als_sales_delivery', function (Blueprint $table) {
            $table->id();
            $table->string('delivery_no',254);
            $table->date('delivery_date');
            $table->bigInteger('order_id')->unsigned();
            $table->bigInteger('vendor_id')->unsigned();
            $table->bigInteger('warehouse_id')->unsigned();
            $table->string('delivery_status',30);
            $table->text('reason')->nullable();
            $table->text('note')->nullable();
            $table->datetime('created_at')->default(\Illuminate\Support\Facades\DB::raw('CURRENT_TIMESTAMP'));;
            $table->integer('created_by');
            $table->datetime('updated_at')->default(\Illuminate\Support\Facades\DB::raw('CURRENT_TIMESTAMP'));;
            $table->integer('updated_by');
            $table->index(['vendor_id','warehouse_id','order_id'],'delivery_order_index_data');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('als_sales_delivery');
    }
}
