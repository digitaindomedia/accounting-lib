<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateAlsPurchaseBastTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('als_purchase_bast', function (Blueprint $table) {
            $table->id();
            $table->string('bast_no',254);
            $table->date('bast_date');
            $table->bigInteger('order_id')->unsigned();
            $table->bigInteger('vendor_id')->unsigned();
            $table->string('bast_status',30);
            $table->text('reason')->nullable();
            $table->text('note')->nullable();
            $table->datetime('created_at')->default(\Illuminate\Support\Facades\DB::raw('CURRENT_TIMESTAMP'));;
            $table->integer('created_by');
            $table->datetime('updated_at')->default(\Illuminate\Support\Facades\DB::raw('CURRENT_TIMESTAMP'));;
            $table->integer('updated_by');
            $table->foreign('order_id')->references('id')->on('als_purchase_order')->onUpdate('cascade')->onDelete('cascade');
            $table->index(['vendor_id']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('als_purchase_bast');
    }
}
