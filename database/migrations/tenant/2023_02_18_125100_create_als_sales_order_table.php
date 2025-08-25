<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateAlsSalesOrderTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('als_sales_order', function (Blueprint $table) {
            $table->id();
            $table->string('order_no',254);
            $table->date('order_date');
            $table->date('date_send');
            $table->text('note')->nullable();
            $table->text('reason')->nullable();
            $table->string('order_status',30);
            $table->bigInteger('vendor_id')->unsigned();
            $table->double('subtotal');
            $table->double('discount');
            $table->string('discount_type',30);
            $table->double('total_discount');
            $table->double('total_tax');
            $table->string('tax_type',40);
            $table->double('total_dpp');
            $table->double('grandtotal');
            $table->string('service_type',60)->nullable();
            $table->string('service_start_period',60)->nullable();
            $table->string('service_end_period',60)->nullable();
            $table->string('order_type',30)->comment('hanya ada 2 tipe yaitu item dan jasa');
            $table->datetime('created_at')->default(\Illuminate\Support\Facades\DB::raw('CURRENT_TIMESTAMP'));;
            $table->integer('created_by');
            $table->datetime('updated_at')->default(\Illuminate\Support\Facades\DB::raw('CURRENT_TIMESTAMP'));;
            $table->integer('updated_by');
            $table->foreign('vendor_id')->references('id')->on('als_vendor')->onUpdate('cascade')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('als_sales_order');
    }
}
