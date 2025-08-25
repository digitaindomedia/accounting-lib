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
        Schema::create('als_aset_tetap_receive', function (Blueprint $table) {
            $table->id();
            $table->string('receive_no', 254)->unique();
            $table->date('receive_date');
            $table->date('penyusutan_date')->nullable();
            $table->unsignedBigInteger('order_id');
            $table->text('note');
            $table->timestamp('created_at')->useCurrent();
            $table->integer('created_by');
            $table->timestamp('updated_at')->useCurrent();
            $table->integer('updated_by');
            $table->string('receive_status', 50);
            $table->integer('susut_skrg')->default(0);
            $table->text('reason')->nullable();
            $table->foreign('order_id')->references('id')->on('als_aset_tetap')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('als_aset_tetap_receive');
    }
};
