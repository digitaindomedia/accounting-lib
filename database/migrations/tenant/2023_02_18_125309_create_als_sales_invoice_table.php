<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateAlsSalesInvoiceTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('als_sales_invoice', function (Blueprint $table) {
            $table->id();
            $table->string('invoice_no',254);
            $table->date('invoice_date');
            $table->date('due_date');
            $table->bigInteger('order_id')->unsigned();
            $table->string('invoice_status',30);
            $table->bigInteger('vendor_id')->unsigned();
            $table->double('dp_nominal')->default(0);
            $table->text('reason')->nullable();
            $table->text('note')->nullable();
            $table->double('subtotal')->default(0);
            $table->double('discount')->default(0);
            $table->string('discount_type',30)->nullable();
            $table->double('discount_total')->default(0);
            $table->double('tax_total')->default(0);
            $table->string('tax_type',40)->nullable();
            $table->double('dpp_total')->default(0);
            $table->double('grandtotal');
            $table->string('invoice_type',30)->comment('hanya ada 2 tipe yaitu item dan jasa');
            $table->string('input_type',30)->comment('hanya ada 2 tipe yaitu penjualan dan jurnal');
            $table->bigInteger('coa_id')->default(0)->unsigned();
            $table->bigInteger('jurnal_id')->unsigned()->default(0);
            $table->bigInteger('warehouse_id')->unsigned()->default(0);
            $table->datetime('created_at')->default(\Illuminate\Support\Facades\DB::raw('CURRENT_TIMESTAMP'));;
            $table->integer('created_by');
            $table->datetime('updated_at')->default(\Illuminate\Support\Facades\DB::raw('CURRENT_TIMESTAMP'));;
            $table->integer('updated_by');
            $table->index(['jurnal_id','coa_id','invoice_date','invoice_no','vendor_id','warehouse_id'],'sales_invoice_index_data');
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
        Schema::dropIfExists('als_sales_invoice');
    }
}
