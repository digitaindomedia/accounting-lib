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
        Schema::create('als_aset_tetap_sales', function (Blueprint $table) {
            $table->id();
            $table->date('sales_date');
            $table->string('sales_no', 254)->unique();
            $table->double('price');
            $table->double('profit_loss');
            $table->text('note');
            $table->timestamps();
            $table->integer('created_by');
            $table->integer('updated_by');
            $table->unsignedBigInteger('profit_loss_coa_id');
            $table->string('sales_status', 180);
            $table->unsignedBigInteger('aset_tetap_id');
            $table->double('nilai_penyusutan')->default(0);
            $table->text('reason')->nullable();
            $table->string('buyer_name', 254)->nullable();

            $table->foreign('profit_loss_coa_id')->references('id')->on('als_coa')->onDelete('cascade');
            $table->foreign('aset_tetap_id')->references('id')->on('als_aset_tetap')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('als_aset_tetap_sales');
    }
};
