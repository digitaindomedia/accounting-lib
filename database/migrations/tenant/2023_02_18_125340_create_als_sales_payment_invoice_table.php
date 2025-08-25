<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateAlsSalesPaymentInvoiceTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('als_sales_payment_invoice', function (Blueprint $table) {
            $table->id();
            $table->string('invoice_no');
            $table->double('total_payment');
            $table->date('payment_date');
            $table->double('total_discount')->default(0);
            $table->mediumText('coa_id_discount')->nullable()->comment('disimpan array json');
            $table->unsignedBigInteger('invoice_id')->default(0);
            $table->unsignedBigInteger('payment_id')->default(0);
            $table->unsignedBigInteger('jurnal_id')->default(0);
            $table->unsignedBigInteger('jurnal_akun_id')->default(0);
            $table->unsignedBigInteger('vendor_id')->default(0);
            $table->unsignedBigInteger('retur_id')->default(0);
            $table->double('total_overpayment')->default(0);
            $table->string('payment_no',254)->nullable();
            $table->mediumText('coa_id_overpayment')->nullable()->comment('disimpan array json');
            $table->index(['invoice_no','payment_date','invoice_id','payment_id','vendor_id','jurnal_akun_id','jurnal_id'],'sales_payment_index_data');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('als_sales_payment_invoice');
    }
}
