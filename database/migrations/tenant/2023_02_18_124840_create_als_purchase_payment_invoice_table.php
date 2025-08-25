<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateAlsPurchasePaymentInvoiceTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('als_purchase_payment_invoice', function (Blueprint $table) {
            $table->id();
            $table->string('invoice_no',254);
            $table->double('total_payment');
            $table->date('payment_date');
            $table->double('total_discount')->default(0);
            $table->mediumText('coa_id_discount')->nullable()->comment('disimpan array json');
            $table->bigInteger('invoice_id')->unsigned()->default(0);
            $table->bigInteger('payment_id')->unsigned()->default(0);
            $table->bigInteger('jurnal_id')->unsigned()->default(0);
            $table->bigInteger('jurnal_akun_id')->unsigned()->default(0);
            $table->bigInteger('vendor_id')->unsigned()->default(0);
            $table->bigInteger('retur_id')->unsigned()->default(0);
            $table->double('total_overpayment')->default(0);
            $table->mediumText('coa_id_overpayment')->nullable()->comment('disimpan array json');
            $table->string('payment_no',254)->nullable();
            $table->index(['invoice_no','payment_date','invoice_id','payment_id','vendor_id','jurnal_akun_id','jurnal_id'],'payment_invoice_index_data');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('als_purchase_payment_invoice');
    }
}
