<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('als_aset_tetap_payment', function (Blueprint $table) {
            $table->id();
            $table->date('payment_date');
            $table->string('payment_no', 254);
            $table->text('note')->nullable();
            $table->double('total')->default(0);
            $table->unsignedBigInteger('invoice_id');
            $table->unsignedBigInteger('payment_method_id');
            $table->string('payment_status', 40);
            $table->text('reason')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->integer('created_by');
            $table->timestamp('updated_at')->useCurrent();
            $table->integer('updated_by');
            $table->string('payment_type', 100)->default('PURCHASE');

            $table->index('invoice_id');
            $table->index('payment_method_id');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('als_aset_tetap_payment');
    }
};
