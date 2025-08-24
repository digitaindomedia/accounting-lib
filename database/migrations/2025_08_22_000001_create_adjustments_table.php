<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        Schema::create('adjustments', function (Blueprint $table) {
            $table->id();
            $table->date('adjustment_date');
            $table->unsignedBigInteger('warehouse_id');
            $table->unsignedBigInteger('coa_adjustment_id');
            $table->string('adjustment_type');
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('adjustments');
    }
};
