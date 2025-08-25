<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateAlsSalesReturTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('als_sales_retur', function (Blueprint $table) {
            $table->id();
            $table->string('retur_no',254);
            $table->date('retur_date');
            $table->text('note')->nullable();
            $table->string('retur_status',30);
            $table->double('subtotal')->default(0);
            $table->double('total_tax')->default(0);
            $table->double('total')->default(0);
            $table->unsignedBigInteger('vendor_id');
            $table->unsignedBigInteger('invoice_id');
            $table->bigInteger('delivery_id')->unsigned();
            $table->text('reason')->nullable();
            $table->datetime('created_at')->default(\Illuminate\Support\Facades\DB::raw('CURRENT_TIMESTAMP'));;
            $table->integer('created_by');
            $table->datetime('updated_at')->default(\Illuminate\Support\Facades\DB::raw('CURRENT_TIMESTAMP'));;
            $table->integer('updated_by');
            $table->index(['vendor_id','invoice_id','delivery_id']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('als_sales_retur');
    }
}
