<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateAlsSalesPaymentTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('als_sales_payment', function (Blueprint $table) {
            $table->id();
            $table->string('payment_no',254);
            $table->date('payment_date');
            $table->text('note')->nullable();
            $table->double('total')->default(0);
            $table->bigInteger('vendor_id')->unsigned();
            $table->bigInteger('payment_method_id')->unsigned();
            $table->string('payment_status',40);
            $table->text('reason')->nullable();
            $table->datetime('created_at')->default(\Illuminate\Support\Facades\DB::raw('CURRENT_TIMESTAMP'));;
            $table->integer('created_by');
            $table->datetime('updated_at')->default(\Illuminate\Support\Facades\DB::raw('CURRENT_TIMESTAMP'));;
            $table->integer('updated_by');
            $table->foreign('payment_method_id')->references('id')->on('als_payment')->onUpdate('cascade')->onDelete('cascade');
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
        Schema::dropIfExists('als_sales_payment');
    }
}
