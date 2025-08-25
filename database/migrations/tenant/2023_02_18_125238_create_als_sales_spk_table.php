<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateAlsSalesSpkTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('als_sales_spk', function (Blueprint $table) {
            $table->id();
            $table->string('spk_no',254);
            $table->date('spk_date');
            $table->unsignedBigInteger('order_id');
            $table->unsignedBigInteger('vendor_id');
            $table->string('spk_status',30);
            $table->text('reason')->nullable();
            $table->text('note')->nullable();
            $table->datetime('created_at')->default(\Illuminate\Support\Facades\DB::raw('CURRENT_TIMESTAMP'));;
            $table->integer('created_by');
            $table->datetime('updated_at')->default(\Illuminate\Support\Facades\DB::raw('CURRENT_TIMESTAMP'));;
            $table->integer('updated_by');
            $table->index(['vendor_id','order_id']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('als_sales_spk');
    }
}
