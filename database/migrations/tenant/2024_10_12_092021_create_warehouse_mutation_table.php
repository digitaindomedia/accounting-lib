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
        Schema::create('als_warehouse_mutation', function (Blueprint $table) {
            $table->id();
            $table->string('ref_no', 254);
            $table->text('note');
            $table->date('mutation_date');
            $table->unsignedBigInteger('from_warehouse_id');
            $table->unsignedBigInteger('to_warehouse_id');
            $table->string('status_mutation', 100);
            $table->string('mutation_type', 30);
            $table->string('reason', 30)->nullable();
            $table->unsignedBigInteger('mutation_out_id');
            $table->dateTime('created_at')->default(now());
            $table->unsignedBigInteger('created_by');
            $table->dateTime('updated_at')->default(now());
            $table->unsignedBigInteger('updated_by');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('als_warehouse_mutation');
    }
};
