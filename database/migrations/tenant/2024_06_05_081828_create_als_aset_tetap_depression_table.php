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
        Schema::create('als_aset_tetap_depression', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('receive_id');
            $table->date('depression_date');
            $table->text('note')->nullable();
            $table->string('jurnal_no', 254);
            $table->double('debet')->default(0);
            $table->double('kredit')->default(0);

            $table->index('receive_id');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('als_aset_tetap_depression');
    }
};
