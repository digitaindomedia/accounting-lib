<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateAlsPurchaseRequestTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('als_purchase_request', function (Blueprint $table) {
            $table->id();
            $table->string('request_no',254);
            $table->date('request_date');
            $table->text('note')->nullable();
            $table->string('request_status',30);
            $table->string('request_from',254)->nullable();
            $table->text('reason')->nullable();
            $table->date('req_needed_date');
            $table->string('urgency',50)->nullable();
            $table->datetime('created_at')->default(\Illuminate\Support\Facades\DB::raw('CURRENT_TIMESTAMP'));;
            $table->integer('created_by');
            $table->datetime('updated_at')->default(\Illuminate\Support\Facades\DB::raw('CURRENT_TIMESTAMP'));;
            $table->integer('updated_by');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('als_purchase_request');
    }
}
