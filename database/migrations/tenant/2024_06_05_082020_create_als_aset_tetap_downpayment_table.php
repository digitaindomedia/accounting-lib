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
        Schema::create('als_aset_tetap_downpayment', function (Blueprint $table) {
            $table->id();
            $table->string('ref_no', 254);
            $table->date('downpayment_date');
            $table->date('faktur_date');
            $table->double('nominal');
            $table->string('downpayment_status', 30);
            $table->string('faktur_accepted', 30);
            $table->string('document', 254);
            $table->string('no_faktur', 254)->nullable();
            $table->text('reason')->nullable();
            $table->text('note')->nullable();
            $table->unsignedBigInteger('order_id');
            $table->bigInteger('coa_id')->unsigned();
            $table->timestamps();
            $table->integer('created_by');
            $table->integer('updated_by');
            $table->bigInteger('tax_id')->unsigned()->default(0);
            $table->mediumText('tax_group')->nullable();
            $table->double('tax_percentage')->default(0);
            $table->double('dpp')->default(0);
            $table->double('ppn')->default(0);

            $table->index('order_id');

            $table->foreign('order_id')
                ->references('id')->on('als_aset_tetap')
                ->onDelete('cascade')
                ->onUpdate('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('als_aset_tetap_downpayment');
    }
};
