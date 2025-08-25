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
        Schema::create('als_stock_usage', function (Blueprint $table) {
            $table->id();
            $table->string('ref_no', 254);
            $table->text('note');
            $table->date('usage_date');
            $table->string('status_usage', 100);
            $table->unsignedBigInteger('warehouse_id');
            $table->text('document')->nullable();
            $table->unsignedBigInteger('coa_id');
            $table->text('reason')->nullable();
            $table->dateTime('created_at')->default(now());
            $table->unsignedBigInteger('created_by');
            $table->dateTime('updated_at')->default(now());
            $table->unsignedBigInteger('updated_by');
            $table->index(['warehouse_id', 'usage_date','coa_id']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('als_stock_usage');
    }
};
