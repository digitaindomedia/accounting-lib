<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateAlsSalesDownpaymentTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('als_sales_downpayment', function (Blueprint $table) {
            $table->id();
            $table->string('ref_no',254);
            $table->date('downpayment_date');
            $table->double('nominal');
            $table->string('downpayment_status',30);
            $table->string('document',254);
            $table->text('reason')->nullable();
            $table->text('note')->nullable();
            $table->bigInteger('order_id')->unsigned();
            $table->bigInteger('coa_id')->unsigned();
            $table->bigInteger('tax_id')->unsigned();
            $table->double('tax_percentage');
            $table->string('dp_type',30)->comment('hanya ada 2 tipe yaitu item dan jasa');
            $table->datetime('created_at')->default(\Illuminate\Support\Facades\DB::raw('CURRENT_TIMESTAMP'));;
            $table->integer('created_by');
            $table->datetime('updated_at')->default(\Illuminate\Support\Facades\DB::raw('CURRENT_TIMESTAMP'));;
            $table->integer('updated_by');
            $table->foreign('order_id')->references('id')->on('als_sales_order')->onUpdate('cascade')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('als_sales_downpayment');
    }
}
