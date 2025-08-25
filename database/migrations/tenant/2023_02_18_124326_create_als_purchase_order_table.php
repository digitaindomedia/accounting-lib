<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateAlsPurchaseOrderTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('als_purchase_order', function (Blueprint $table) {
            $table->id();
            $table->string('order_no',254);
            $table->date('order_date');
            $table->date('date_send');
            $table->bigInteger('request_id')->unsigned();
            $table->bigInteger('coa_id')->unsigned();
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
            $table->string('order_type',30)->comment('hanya ada 2 tipe yaitu item dan jasa');
            $table->datetime('created_at')->default(\Illuminate\Support\Facades\DB::raw('CURRENT_TIMESTAMP'));;
            $table->integer('created_by');
            $table->datetime('updated_at')->default(\Illuminate\Support\Facades\DB::raw('CURRENT_TIMESTAMP'));;
            $table->integer('updated_by');
            $table->index(['request_id','coa_id']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('als_purchase_order');
    }
}
